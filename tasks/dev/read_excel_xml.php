<?php
// Script temporal para leer la estructura básica de un archivo Excel .xlsx
// dado que no disponemos de librerías avanzadas y .xlsx es un archivo ZIP que contiene XMLs.

$xlsxPath = "F:\\Nueva carpeta\\Formulario_Solicitud_de_Codigo2026-04-30_19_59_09.xlsx";

if (!file_exists($xlsxPath)) {
    die("El archivo no existe en la ruta especificada.\n");
}

$zip = new ZipArchive();
if ($zip->open($xlsxPath) !== TRUE) {
    die("No se pudo abrir el archivo XLSX como ZIP.\n");
}

// 1. Obtener strings compartidos para decodificar índices de texto
$sharedStrings = [];
$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
if ($sharedStringsXml) {
    $xml = simplexml_load_string($sharedStringsXml);
    foreach ($xml->si as $si) {
        $sharedStrings[] = (string)($si->t ?? $si->r->t ?? '');
    }
}

echo "Se cargaron " . count($sharedStrings) . " cadenas de texto compartidas.\n\n";

// 2. Obtener datos de la primera hoja (sheet1.xml)
$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
if (!$sheetXml) {
    die("No se pudo encontrar xl/worksheets/sheet1.xml en el archivo.\n");
}

$xmlSheet = simplexml_load_string($sheetXml);
$rows = [];

// Recorrer las filas y celdas
foreach ($xmlSheet->sheetData->row as $row) {
    $rowNum = (int)$row['r'];
    $rowData = [];
    foreach ($row->c as $c) {
        $cellRef = (string)$c['r'];
        $colRef = preg_replace('/[0-9]/', '', $cellRef);
        $type = (string)$c['t'];
        $val = (string)$c->v;
        
        $finalVal = '';
        if ($type === 's') {
            $finalVal = $sharedStrings[(int)$val] ?? '';
        } else {
            $finalVal = $val;
        }
        
        $rowData[$colRef] = $finalVal;
    }
    $rows[$rowNum] = $rowData;
}

$zip->close();

// Mostrar las primeras 50 filas con datos legibles
echo "=== PRIMERAS 50 FILAS DEL EXCEL ===\n";
$printed = 0;
foreach ($rows as $rNum => $rData) {
    if (empty($rData)) continue;
    echo "Fila #$rNum: ";
    print_r($rData);
    $printed++;
    if ($printed >= 50) break;
}
?>
