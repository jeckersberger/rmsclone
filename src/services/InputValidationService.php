<?php

/**
 * InputValidationService
 *
 * Static utility methods for validating and sanitizing user input.
 * Use these methods instead of accessing $_POST/$_GET values directly
 * to prevent SQL injection, XSS, and other input-based attacks.
 */
class InputValidationService
{
    /**
     * Validate and cast a value to integer.
     *
     * @param mixed $value The input value
     * @param int $default Default value if validation fails
     * @return int
     */
    public static function int($value, int $default = 0): int
    {
        if ($value === null || $value === '' || $value === false) {
            return $default;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        return $filtered !== false ? $filtered : $default;
    }

    /**
     * Validate and sanitize a string value, trimming and truncating to max length.
     *
     * @param mixed $value The input value
     * @param int $maxLength Maximum allowed string length
     * @return string
     */
    public static function string($value, int $maxLength = 255): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        $str = trim((string) $value);
        $str = strip_tags($str);
        if (mb_strlen($str) > $maxLength) {
            $str = mb_substr($str, 0, $maxLength);
        }
        return $str;
    }

    /**
     * Validate an email address.
     *
     * @param mixed $value The input value
     * @return string|null Valid email or null
     */
    public static function email($value): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $email = filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL);
        return $email !== false ? $email : null;
    }

    /**
     * Validate a date string in YYYY-MM-DD format.
     *
     * @param mixed $value The input value
     * @return string|null Valid date string or null
     */
    public static function date($value): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $str = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            return null;
        }
        $parts = explode('-', $str);
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return null;
        }
        return $str;
    }

    /**
     * Validate that a value is one of a set of allowed values.
     *
     * @param mixed $value The input value
     * @param array $allowed Array of allowed values
     * @param mixed $default Default value if not in allowed set
     * @return mixed The validated value or default
     */
    public static function enum($value, array $allowed, $default = null)
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Validate and cast a value to a positive integer (>= 1).
     *
     * @param mixed $value The input value
     * @param int $default Default value if validation fails
     * @return int
     */
    public static function positiveInt($value, int $default = 0): int
    {
        $int = self::int($value, $default);
        return $int >= 1 ? $int : $default;
    }

    /**
     * Validate and cast a value to float.
     *
     * @param mixed $value The input value
     * @param float $default Default value if validation fails
     * @return float
     */
    public static function float($value, float $default = 0.0): float
    {
        if ($value === null || $value === '' || $value === false) {
            return $default;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $filtered !== false ? $filtered : $default;
    }

    /**
     * Validate and decode a JSON string into an array.
     *
     * @param mixed $value The input value (JSON string)
     * @return array|null Decoded array or null on invalid JSON
     */
    public static function json($value): ?array
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $decoded = json_decode((string) $value, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }
        return $decoded;
    }
}
