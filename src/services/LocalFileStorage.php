<?php
/**
 * Lokaler Dateispeicher fuer Eingangsrechnungen, Vertraege, Schadensfotos.
 * Speichert Dateien unter /data/uploads/{instanceId}/{type}/{year-month}/
 */
class LocalFileStorage
{
    private const BASE_DIR = '/data/uploads';

    /**
     * Store an uploaded file locally
     * @return array ['path' => relative path, 'size' => bytes, 'mime' => mime type]
     */
    public static function store(int $instanceId, string $type, array $file): array
    {
        $dir = self::buildDir($instanceId, $type);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin';
        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $fullPath = $dir . '/' . $safeName;

        if (isset($file['tmp_name'])) {
            move_uploaded_file($file['tmp_name'], $fullPath);
        } elseif (isset($file['content'])) {
            file_put_contents($fullPath, $file['content']);
        }

        $relPath = self::relativePath($instanceId, $type, $safeName);

        return [
            'path' => $relPath,
            'full_path' => $fullPath,
            'size' => filesize($fullPath),
            'mime' => mime_content_type($fullPath) ?: ($file['type'] ?? 'application/octet-stream'),
            'original_name' => $file['name'],
        ];
    }

    /**
     * Read file contents
     */
    public static function read(string $relativePath): ?string
    {
        $full = self::BASE_DIR . '/' . $relativePath;
        if (!file_exists($full)) return null;
        return file_get_contents($full);
    }

    /**
     * Read file as base64
     */
    public static function readBase64(string $relativePath): ?string
    {
        $content = self::read($relativePath);
        return $content ? base64_encode($content) : null;
    }

    /**
     * Delete a file
     */
    public static function delete(string $relativePath): bool
    {
        $full = self::BASE_DIR . '/' . $relativePath;
        if (file_exists($full)) return unlink($full);
        return false;
    }

    /**
     * Get the full filesystem path
     */
    public static function fullPath(string $relativePath): string
    {
        return self::BASE_DIR . '/' . $relativePath;
    }

    private static function buildDir(int $instanceId, string $type): string
    {
        return self::BASE_DIR . '/' . $instanceId . '/' . $type . '/' . date('Y-m');
    }

    private static function relativePath(int $instanceId, string $type, string $filename): string
    {
        return $instanceId . '/' . $type . '/' . date('Y-m') . '/' . $filename;
    }
}
