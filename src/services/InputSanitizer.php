<?php
/**
 * Input Sanitization Service
 *
 * Provides consistent, centralized input sanitization for all user input.
 * Use this service instead of ad-hoc sanitization scattered across the codebase.
 *
 * Usage:
 *   $sanitizer = new InputSanitizer();
 *   $name  = $sanitizer->sanitizeString($_POST['name']);
 *   $email = $sanitizer->sanitizeEmail($_POST['email']);
 *   $id    = $sanitizer->sanitizeInt($_POST['id']);
 *
 *   // Bulk sanitize with rules:
 *   $clean = $sanitizer->sanitizeArray($_POST, [
 *       'name'  => 'string',
 *       'email' => 'email',
 *       'id'    => 'int',
 *       'price' => 'float',
 *       'file'  => 'filename',
 *   ]);
 */
class InputSanitizer
{
    /**
     * Sanitize a string: trim whitespace and encode HTML special characters.
     * Prevents XSS by converting <, >, &, ", ' to HTML entities.
     *
     * @param mixed $input Raw input value
     * @return string Sanitized string
     */
    public function sanitizeString($input): string
    {
        if ($input === null || $input === false) {
            return '';
        }
        return htmlspecialchars(trim((string)$input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize an email address using PHP's built-in filter.
     * Removes all characters except letters, digits, and !#$%&'*+/=?^_`{|}~@.[]-
     *
     * @param mixed $input Raw email input
     * @return string Sanitized email string (may be empty if totally invalid)
     */
    public function sanitizeEmail($input): string
    {
        if ($input === null || $input === false) {
            return '';
        }
        $sanitized = filter_var(trim((string)$input), FILTER_SANITIZE_EMAIL);
        return $sanitized !== false ? $sanitized : '';
    }

    /**
     * Sanitize input to an integer value.
     *
     * @param mixed $input Raw input value
     * @return int Integer value (0 if non-numeric)
     */
    public function sanitizeInt($input): int
    {
        return intval($input);
    }

    /**
     * Sanitize input to a float value.
     *
     * @param mixed $input Raw input value
     * @return float Float value (0.0 if non-numeric)
     */
    public function sanitizeFloat($input): float
    {
        return floatval($input);
    }

    /**
     * Sanitize a filename by removing path traversal characters and dangerous patterns.
     * - Strips directory separators (/, \)
     * - Removes null bytes
     * - Removes path traversal sequences (..)
     * - Removes leading dots (hidden files)
     * - Only allows alphanumeric, dash, underscore, dot, space
     *
     * @param mixed $input Raw filename
     * @return string Safe filename
     */
    public function sanitizeFilename($input): string
    {
        if ($input === null || $input === false) {
            return '';
        }

        $filename = (string)$input;

        // Remove null bytes
        $filename = str_replace("\0", '', $filename);

        // Remove any directory components
        $filename = basename($filename);

        // Remove path traversal sequences
        $filename = str_replace('..', '', $filename);

        // Remove directory separators that may have survived
        $filename = str_replace(['/', '\\'], '', $filename);

        // Remove leading dots (hidden files on Unix)
        $filename = ltrim($filename, '.');

        // Only allow safe characters: alphanumeric, dash, underscore, dot, space
        $filename = preg_replace('/[^a-zA-Z0-9\-_\. ]/', '', $filename);

        // Collapse multiple dots
        $filename = preg_replace('/\.{2,}/', '.', $filename);

        // Trim whitespace
        $filename = trim($filename);

        // If nothing remains, return a safe default
        if ($filename === '' || $filename === '.') {
            return 'unnamed_file';
        }

        return $filename;
    }

    /**
     * Validate that all required fields are present and non-empty in the data array.
     *
     * @param array $fields List of required field names
     * @param array $data   Associative array of data to check
     * @return array ['valid' => bool, 'missing' => string[]] - list of missing field names
     */
    public function validateRequired(array $fields, array $data): array
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[] = $field;
            }
        }
        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Bulk sanitize an associative array based on a rules array.
     *
     * Supported rule types:
     *   'string'   => sanitizeString()
     *   'email'    => sanitizeEmail()
     *   'int'      => sanitizeInt()
     *   'float'    => sanitizeFloat()
     *   'filename' => sanitizeFilename()
     *   'bool'     => cast to boolean
     *   'raw'      => no sanitization (use sparingly)
     *
     * Fields not listed in $rules are excluded from the output.
     *
     * @param array $data  Raw input data
     * @param array $rules Associative array ['field_name' => 'type', ...]
     * @return array Sanitized data with only the fields specified in $rules
     */
    public function sanitizeArray(array $data, array $rules): array
    {
        $sanitized = [];
        foreach ($rules as $field => $type) {
            $value = $data[$field] ?? null;

            switch ($type) {
                case 'string':
                    $sanitized[$field] = $this->sanitizeString($value);
                    break;
                case 'email':
                    $sanitized[$field] = $this->sanitizeEmail($value);
                    break;
                case 'int':
                    $sanitized[$field] = $this->sanitizeInt($value);
                    break;
                case 'float':
                    $sanitized[$field] = $this->sanitizeFloat($value);
                    break;
                case 'filename':
                    $sanitized[$field] = $this->sanitizeFilename($value);
                    break;
                case 'bool':
                    $sanitized[$field] = (bool)$value;
                    break;
                case 'raw':
                    $sanitized[$field] = $value;
                    break;
                default:
                    // Unknown type: default to string sanitization for safety
                    $sanitized[$field] = $this->sanitizeString($value);
                    break;
            }
        }
        return $sanitized;
    }
}
