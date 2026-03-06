<?php
/**
 * S3Files helper - Stores project files (PDFs) to local storage.
 */
class S3Files {
    public static function storeProjectFile($db, int $instanceId, int $projectId, int $fileType, array $opts): array {
        $storageRoot = getenv('LOCAL_STORAGE_PATH') ?: '/var/www/html/storage';
        $paths = [20 => 'uploads/PROJECT_INVOICES', 21 => 'uploads/PROJECT_QUOTES', 22 => 'uploads/PROJECT_DELIVERY_NOTES'];
        $storagePath = $paths[$fileType] ?? 'uploads/PROJECT_DOCS';

        $fullDir = $storageRoot . '/' . $storagePath;
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0775, true);
        }

        $filename = ($opts['name'] ?? 'doc') . '_' . time() . '_' . mt_rand(10000, 99999);
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
        $fullPath = $fullDir . '/' . $safeFilename . '.pdf';

        file_put_contents($fullPath, $opts['content']);

        $fileData = [
            's3files_extension' => 'pdf',
            's3files_path' => $storagePath,
            's3files_meta_size' => strlen($opts['content']),
            's3files_meta_type' => $fileType,
            's3files_meta_subType' => $projectId,
            'users_userid' => 0,
            's3files_original_name' => ($opts['name'] ?? 'document') . '.pdf',
            's3files_filename' => $safeFilename,
            's3files_name' => $opts['name'] ?? 'Dokument',
            's3files_meta_public' => 0,
            'instances_id' => $instanceId,
        ];

        $id = $db->insert('s3files', $fileData);
        if (!$id) throw new \RuntimeException('Fehler beim Speichern der PDF-Datei.');

        return ['s3files_id' => $id, 'path' => $fullPath];
    }
}
