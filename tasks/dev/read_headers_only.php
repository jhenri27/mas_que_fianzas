<?php
$xlsxPath = "F:\\Nueva carpeta\\Formulario_Solicitud_de_Codigo2026-04-30_19_59_09.xlsx";
$zip = new ZipArchive();
if ($zip->open($xlsxPath) === TRUE) {
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml) {
        $xml = simplexml_load_string($sharedStringsXml);
        foreach ($xml->si as $si) {
            $sharedStrings[] = (string)($si->t ?? $si->r->t ?? '');
        }
    }
    
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml) {
        $xmlSheet = simplexml_load_string($sheetXml);
        foreach ($xmlSheet->sheetData->row as $row) {
            $rowNum = (int)$row['r'];
            if ($rowNum === 1) {
                echo "Row 1:\n";
                foreach ($row->c as $c) {
                    $cellRef = (string)$c['r'];
                    $colRef = preg_replace('/[0-9]/', '', $cellRef);
                    $type = (string)$c['t'];
                    $val = (string)$c->v;
                    $finalVal = ($type === 's') ? ($sharedStrings[(int)$val] ?? '') : $val;
                    echo "[$colRef] => '$finalVal'\n";
                }
                break;
            }
        }
    }
    $zip->close();
}
?>
