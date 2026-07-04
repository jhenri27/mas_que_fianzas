<?php
/**
 * Motor MELCA-Fixuper - Constructor de Parches (Builder)
 * Empaqueta mejoras, migraciones y actualizaciones en formato ZIP firmado.
 */
class MelcaBuilder {
    private $secretKey;
    private $outputDir;
    private $basePath;

    public function __construct($secretKey, $outputDir, $basePath) {
        $this->secretKey = $secretKey;
        $this->outputDir = $outputDir;
        $this->basePath = $basePath;
    }

    public function buildPatch($version, $description, $filesToInclude = [], $sqlQueries = []) {
        $patchId = "patch_" . str_replace('.', '_', $version) . "_" . time();
        $zipPath = $this->outputDir . DIRECTORY_SEPARATOR . $patchId . ".zip";
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("No se pudo crear el archivo ZIP del parche.");
        }

        // Crear manifiesto
        $manifest = [
            "version" => $version,
            "description" => $description,
            "timestamp" => time(),
            "sql_migrations" => !empty($sqlQueries),
            "files" => $filesToInclude
        ];
        
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        
        // Agregar queries SQL si existen
        if (!empty($sqlQueries)) {
            $sqlContent = implode(";\n", $sqlQueries) . ";";
            $zip->addFromString('migrations.sql', $sqlContent);
        }

        // Agregar archivos reales
        foreach ($filesToInclude as $file) {
            // Se asume que $file es relativo a la raiz del proyecto
            $absPath = realpath($this->basePath . DIRECTORY_SEPARATOR . $file);
            if ($absPath && file_exists($absPath) && !is_dir($absPath)) {
                // Guardarlo dentro del ZIP en la misma estructura relativa
                $zip->addFile($absPath, "files/" . ltrim($file, '/\\'));
            } else {
                throw new Exception("Archivo no encontrado para empaquetar: $file");
            }
        }
        
        $zip->close();
        
        // Firmar el ZIP criptográficamente (HMAC-SHA256 con la Secret Key)
        $fileHash = hash_file('sha256', $zipPath);
        $signature = hash_hmac('sha256', $fileHash, $this->secretKey);
        
        // Generar archivo META con la firma
        $metaData = [
            "file" => basename($zipPath),
            "hash" => $fileHash,
            "signature" => $signature
        ];
        
        file_put_contents($this->outputDir . DIRECTORY_SEPARATOR . $patchId . ".meta.json", json_encode($metaData, JSON_PRETTY_PRINT));
        
        return [
            "exito" => true,
            "patch_id" => $patchId,
            "zip_path" => $zipPath,
            "meta" => $metaData
        ];
    }
}
