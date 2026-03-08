<?php
/**
 * Upload Validation Service
 *
 * Validates uploaded files for security before they are stored.
 * Checks file size, MIME type (via finfo, not just extension), extension whitelist,
 * filename safety, and scans for embedded PHP code to prevent web shell uploads.
 *
 * Usage:
 *   $validator = new UploadValidationService();
 *
 *   // Validate with a predefined profile
 *   $result = $validator->validateUpload($_FILES['photo'], 'image');
 *
 *   // Validate with custom types and size
 *   $result = $validator->validateUpload($_FILES['doc'], ['application/pdf'], 5 * 1024 * 1024);
 *
 *   if ($result['valid']) {
 *       $validator->moveUpload($_FILES['photo'], '/path/to/uploads/photo.jpg');
 *   } else {
 *       echo $result['error'];
 *   }
 */
class UploadValidationService
{
    /** Default maximum file size: 10 MB */
    const DEFAULT_MAX_SIZE = 10 * 1024 * 1024;

    /**
     * Predefined upload profiles mapping to allowed MIME types.
     */
    private static $profiles = [
        'image' => [
            'types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        ],
        'document' => [
            'types' => ['application/pdf', 'text/csv', 'application/xml', 'text/xml'],
            'extensions' => ['pdf', 'csv', 'xml'],
        ],
        'import' => [
            'types' => [
                'text/csv',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
            ],
            'extensions' => ['csv', 'xls', 'xlsx', 'txt'],
        ],
    ];

    /**
     * Map of MIME types to their allowed extensions.
     */
    private static $mimeToExtension = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'text/csv' => ['csv'],
        'text/plain' => ['txt', 'csv'],
        'application/xml' => ['xml'],
        'text/xml' => ['xml'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
    ];

    /**
     * Validate an uploaded file.
     *
     * @param array        $file          The $_FILES['fieldname'] array
     * @param string|array $allowedTypes  A profile name ('image', 'document', 'import')
     *                                    or an array of allowed MIME types
     * @param int          $maxSize       Maximum file size in bytes (default 10MB)
     * @return array ['valid' => bool, 'error' => string|null, 'mime' => string|null, 'extension' => string|null]
     */
    public function validateUpload(array $file, $allowedTypes = 'image', int $maxSize = self::DEFAULT_MAX_SIZE): array
    {
        // Check for upload errors
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = $this->getUploadErrorMessage($file['error'] ?? -1);
            return ['valid' => false, 'error' => 'Upload error: ' . $errorMessage, 'mime' => null, 'extension' => null];
        }

        // Check that the file was actually uploaded via HTTP POST
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File was not uploaded via HTTP POST.', 'mime' => null, 'extension' => null];
        }

        // Resolve profile to types and extensions
        $resolvedTypes = [];
        $resolvedExtensions = [];
        if (is_string($allowedTypes) && isset(self::$profiles[$allowedTypes])) {
            $resolvedTypes = self::$profiles[$allowedTypes]['types'];
            $resolvedExtensions = self::$profiles[$allowedTypes]['extensions'];
        } elseif (is_array($allowedTypes)) {
            $resolvedTypes = $allowedTypes;
            // Derive extensions from MIME types
            foreach ($allowedTypes as $mime) {
                if (isset(self::$mimeToExtension[$mime])) {
                    $resolvedExtensions = array_merge($resolvedExtensions, self::$mimeToExtension[$mime]);
                }
            }
            $resolvedExtensions = array_unique($resolvedExtensions);
        } else {
            return ['valid' => false, 'error' => 'Invalid upload profile or allowed types.', 'mime' => null, 'extension' => null];
        }

        // 1. File size check
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / (1024 * 1024), 1);
            return ['valid' => false, 'error' => "File exceeds maximum size of {$maxMB}MB.", 'mime' => null, 'extension' => null];
        }

        if ($file['size'] === 0) {
            return ['valid' => false, 'error' => 'Uploaded file is empty.', 'mime' => null, 'extension' => null];
        }

        // 2. MIME type check using finfo (not the user-supplied type)
        $detectedMime = $this->detectMimeType($file['tmp_name']);
        if ($detectedMime === false) {
            return ['valid' => false, 'error' => 'Could not determine file type.', 'mime' => null, 'extension' => null];
        }

        if (!in_array($detectedMime, $resolvedTypes, true)) {
            return ['valid' => false, 'error' => "File type '{$detectedMime}' is not allowed.", 'mime' => $detectedMime, 'extension' => null];
        }

        // 3. Extension whitelist check
        $extension = $this->getFileExtension($file['name']);
        if (!in_array($extension, $resolvedExtensions, true)) {
            return ['valid' => false, 'error' => "File extension '.{$extension}' is not allowed.", 'mime' => $detectedMime, 'extension' => $extension];
        }

        // 4. Filename sanitization checks
        $filenameCheck = $this->validateFilename($file['name']);
        if (!$filenameCheck['valid']) {
            return ['valid' => false, 'error' => $filenameCheck['error'], 'mime' => $detectedMime, 'extension' => $extension];
        }

        // 5. Check for PHP code in file content (prevent web shell uploads)
        if ($this->containsPhpCode($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File contains potentially dangerous code.', 'mime' => $detectedMime, 'extension' => $extension];
        }

        return ['valid' => true, 'error' => null, 'mime' => $detectedMime, 'extension' => $extension];
    }

    /**
     * Get the list of allowed image MIME types.
     *
     * @return string[]
     */
    public function getAllowedImageTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    }

    /**
     * Get the list of allowed document MIME types.
     *
     * @return string[]
     */
    public function getAllowedDocumentTypes(): array
    {
        return ['application/pdf', 'text/csv', 'application/xml', 'text/xml'];
    }

    /**
     * Safely move an uploaded file to the destination path.
     * Creates the destination directory if it doesn't exist.
     *
     * @param array  $file        The $_FILES entry
     * @param string $destination Full path for the destination file
     * @return array ['success' => bool, 'error' => string|null, 'path' => string|null]
     */
    public function moveUpload(array $file, string $destination): array
    {
        // Ensure destination doesn't contain path traversal
        $realDir = realpath(dirname($destination));
        if ($realDir === false) {
            // Directory doesn't exist, try to create it
            $dir = dirname($destination);
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['success' => false, 'error' => 'Could not create destination directory.', 'path' => null];
            }
            $realDir = realpath($dir);
        }

        // Ensure the real path doesn't escape via symlinks to unexpected locations
        $destFile = $realDir . DIRECTORY_SEPARATOR . basename($destination);

        if (!is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'Source is not a valid uploaded file.', 'path' => null];
        }

        if (!move_uploaded_file($file['tmp_name'], $destFile)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file.', 'path' => null];
        }

        // Set safe permissions (read/write for owner, read for group, no exec)
        chmod($destFile, 0644);

        return ['success' => true, 'error' => null, 'path' => $destFile];
    }

    /**
     * Detect the actual MIME type of a file using finfo (libmagic).
     *
     * @param string $filePath Path to the file
     * @return string|false Detected MIME type, or false on failure
     */
    private function detectMimeType(string $filePath)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($filePath);

        return $mime !== false ? $mime : false;
    }

    /**
     * Get the lowercase file extension from a filename.
     *
     * @param string $filename Original filename
     * @return string Lowercase extension without the dot
     */
    private function getFileExtension(string $filename): string
    {
        // Remove null bytes first
        $filename = str_replace("\0", '', $filename);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $ext;
    }

    /**
     * Validate a filename for security issues.
     *
     * @param string $filename The original filename
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validateFilename(string $filename): array
    {
        // Check for null bytes (used in null byte injection attacks)
        if (strpos($filename, "\0") !== false) {
            return ['valid' => false, 'error' => 'Filename contains null bytes.'];
        }

        // Check for path traversal
        if (strpos($filename, '..') !== false) {
            return ['valid' => false, 'error' => 'Filename contains path traversal sequence.'];
        }

        // Check for directory separators
        if (preg_match('#[/\\\\]#', $filename)) {
            return ['valid' => false, 'error' => 'Filename contains directory separators.'];
        }

        // Check for double extensions that could be exploited (e.g., file.php.jpg on misconfigured servers)
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'shtml', 'cgi', 'pl', 'py', 'jsp', 'asp', 'aspx', 'sh', 'bash'];
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            // Check all parts except the last (actual extension) for dangerous extensions
            for ($i = 1; $i < count($parts) - 1; $i++) {
                if (in_array(strtolower($parts[$i]), $dangerousExtensions, true)) {
                    return ['valid' => false, 'error' => 'Filename contains a dangerous extension in a multi-extension name.'];
                }
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Check if a file contains PHP code patterns.
     * Scans the first 8KB for common PHP opening tags and patterns.
     *
     * @param string $filePath Path to the file
     * @return bool True if PHP code patterns are detected
     */
    private function containsPhpCode(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        // Read first 8KB - web shells are usually small or have PHP at the start
        $content = file_get_contents($filePath, false, null, 0, 8192);
        if ($content === false) {
            return false;
        }

        // Also read last 2KB in case PHP is appended
        $fileSize = filesize($filePath);
        if ($fileSize > 8192) {
            $tail = file_get_contents($filePath, false, null, $fileSize - 2048);
            if ($tail !== false) {
                $content .= $tail;
            }
        }

        // Check for PHP opening tags and common shell patterns
        $patterns = [
            '<\?php',
            '<\?=',
            '<\?[\s\n\r]',
            '<script\s+language\s*=\s*["\']?php["\']?',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a human-readable upload error message.
     *
     * @param int $errorCode PHP upload error code
     * @return string Error description
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload_max_filesize limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form MAX_FILE_SIZE limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload stopped by a PHP extension.',
        ];

        return $messages[$errorCode] ?? 'Unknown upload error (code: ' . $errorCode . ').';
    }
}
