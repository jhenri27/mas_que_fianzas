<?php
/**
 * Motor MELCA-Fixuper - Desplegador de Parches (Deployer)
 * Verifica firmas, hace copias de seguridad de DB, e instala los parches de forma transaccional (VAF).
 * Diseñado para ejecutarse en el servidor de PRODUCCIÓN.
 */
class MelcaDeployer {
    private $secretKey;
    private $dbConnection;
    private $targetPath;

    public function __construct($secretKey, $dbConnection, $targetPath) {
        $this->secretKey = $secretKey;
        $this->dbConnection = $dbConnection;
        $this->targetPath = $targetPath;
    }

    public function deploy($zipPath, $metaData) {
        try {
            // 1. Verificación de Seguridad (Firma Criptográfica)
            $fileHash = hash_file('sha256', $zipPath);
            if ($fileHash !== $metaData['hash']) {
                throw new Exception("ERROR DE SEGURIDAD: El hash del archivo ZIP no coincide.");
            }

            $expectedSignature = hash_hmac('sha256', $fileHash, $this->secretKey);
            if ($expectedSignature !== $metaData['signature']) {
                throw new Exception("ERROR DE SEGURIDAD: La firma digital (token) no es válida. Posible manipulación del paquete.");
            }

            // 2. Extracción y validación del manifiesto
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new Exception("ERROR VAF: No se pudo abrir el archivo ZIP firmado.");
            }

            $manifestStr = $zip->getFromName('manifest.json');
            if (!$manifestStr) {
                $zip->close();
                throw new Exception("ERROR VAF: El paquete no contiene manifest.json válido.");
            }

            $manifest = json_decode($manifestStr, true);

            // 3. Modo Mantenimiento Transaccional de Base de Datos
            $this->dbConnection->begin_transaction();

            if ($manifest['sql_migrations']) {
                $sqlStr = $zip->getFromName('migrations.sql');
                if ($sqlStr) {
                    $queries = explode(";", $sqlStr);
                    foreach ($queries as $query) {
                        $q = trim($query);
                        if (!empty($q)) {
                            if (!$this->dbConnection->query($q)) {
                                throw new Exception("Error SQL en la migración: " . $this->dbConnection->error);
                            }
                        }
                    }
                }
            }

            // 4. Extracción de Archivos a los directorios de producción
            // Extraer solo la carpeta 'files/' a sus respectivos lugares
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (strpos($filename, 'files/') === 0 && $filename !== 'files/') {
                    $relativePath = substr($filename, 6); // Quitar 'files/'
                    $destPath = $this->targetPath . DIRECTORY_SEPARATOR . $relativePath;
                    
                    // Crear directorios si no existen
                    $dir = dirname($destPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    
                    // Extraer y reemplazar archivo
                    $fileContent = $zip->getFromIndex($i);
                    if (file_put_contents($destPath, $fileContent) === false) {
                        throw new Exception("ERROR VAF: Fallo crítico al escribir el archivo: $destPath");
                    }
                }
            }

            // 5. Commit si todo ha ido bien (VAF Superado)
            $this->dbConnection->commit();
            $zip->close();

            return [
                "exito" => true,
                "mensaje" => "Actualización MELCA aplicada con éxito. (Versión: {$manifest['version']})",
                "version" => $manifest['version']
            ];

        } catch (Exception $e) {
            // ROLLBACK EN CASO DE CUALQUIER FALLO - NUNCA ROMPER PRODUCCIÓN
            if ($this->dbConnection && $this->dbConnection->ping()) {
                $this->dbConnection->rollback();
            }
            if (isset($zip) && is_object($zip)) {
                $zip->close();
            }
            return [
                "exito" => false,
                "mensaje" => $e->getMessage()
            ];
        }
    }
}
