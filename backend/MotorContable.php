<?php
/**
 * CLASE: MotorContable
 * ROL: Orquestador de Eventos Contables Automáticos
 * VERSION: 3.1-PRO
 */

namespace MQF\Finance;

require_once __DIR__ . '/ContabilidadManager.php';

class MotorContable {

    /**
     * Dispara un evento contable automático
     * @param string $evento Nombre del evento (ej: 'EMISION_POLIZA')
     * @param array $datos Datos necesarios para el asiento (monto, cliente_id, etc.)
     * @return int|bool ID del asiento o false
     */
    public static function disparar($evento, $datos) {
        $mgr = ContabilidadManager::getInstance();
        
        try {
            // 1. Obtener regla de asiento (En producción vendría de cf_reglas_asiento)
            $regla = self::getReglaParaEvento($evento, $datos);
            
            if (!$regla) {
                error_log("MotorContable: No se encontró regla para el evento $evento");
                return false;
            }

            // 2. Construir encabezado
            $header = [
                'fecha' => $datos['fecha'] ?? date('Y-m-d'),
                'descripcion' => $regla['descripcion_format'],
                'tipo' => 'AUTOMATICO',
                'origen_modulo' => $datos['modulo'],
                'origen_id' => $datos['id']
            ];

            // 3. Construir líneas dinámicamente
            $lineas = [];
            foreach ($regla['lineas'] as $l) {
                $monto = self::calcularMonto($l['formula'], $datos);
                if ($monto <= 0) continue;

                $lineas[] = [
                    'cuenta_codigo' => $l['cuenta'],
                    'descripcion' => $l['descripcion'] ?? $header['descripcion'],
                    'debe' => ($l['posicion'] === 'DB') ? $monto : 0,
                    'haber' => ($l['posicion'] === 'CR') ? $monto : 0
                ];
            }

            // 4. Crear Asiento
            return $mgr->crearAsiento($header, $lineas);

        } catch (\Exception $e) {
            error_log("MotorContable Error [$evento]: " . $e->getMessage());
            // En plan de emergencia, no bloqueamos la operación principal
            return false;
        }
    }

    /**
     * Definición de reglas (Hardcoded para arranque rápido de emergencia)
     */
    private static function getReglaParaEvento($evento, $datos) {
        $reglas = [
            'EMISION_POLIZA' => [
                'descripcion_format' => "Emisión Póliza #" . ($datos['numero'] ?? 'S/N'),
                'lineas' => [
                    ['cuenta' => '1.1.02', 'posicion' => 'DB', 'formula' => 'monto_total'],   // Primas por Cobrar
                    ['cuenta' => '2.1.01', 'posicion' => 'CR', 'formula' => 'monto_neto'],    // Primas por Pagar Aseg.
                    ['cuenta' => '4.1.01', 'posicion' => 'CR', 'formula' => 'comision'],      // Ingresos Comisiones
                    ['cuenta' => '2.1.02', 'posicion' => 'CR', 'formula' => 'itbis']          // ITBIS por Pagar
                ]
            ],
            'COBRO_PRIMA' => [
                'descripcion_format' => "Cobro Prima Póliza #" . ($datos['numero'] ?? 'S/N'),
                'lineas' => [
                    ['cuenta' => '1.1.01', 'posicion' => 'DB', 'formula' => 'monto_cobrado'], // Caja y Bancos
                    ['cuenta' => '1.1.02', 'posicion' => 'CR', 'formula' => 'monto_cobrado']  // Primas por Cobrar
                ]
            ],
            'PAGO_COMISION_AGENTE' => [
                'descripcion_format' => "Pago Comisión Agente: " . ($datos['agente'] ?? 'S/N'),
                'lineas' => [
                    ['cuenta' => '5.1.01', 'posicion' => 'DB', 'formula' => 'monto_bruto'],   // Gastos Comisiones
                    ['cuenta' => '1.1.01', 'posicion' => 'CR', 'formula' => 'monto_neto'],    // Caja y Bancos (Pago)
                    ['cuenta' => '2.1.03', 'posicion' => 'CR', 'formula' => 'retencion_isr']  // ISR Retenido 10%
                ]
            ]
        ];

        return $reglas[$evento] ?? null;
    }

    /**
     * Evalúa la fórmula o campo de datos para obtener el monto
     */
    private static function calcularMonto($formula, $datos) {
        if (isset($datos[$formula])) {
            return (float)$datos[$formula];
        }
        
        // Aquí se podrían añadir cálculos complejos si la fórmula es una expresión
        return 0;
    }
}
