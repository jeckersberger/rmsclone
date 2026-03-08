<?php
/**
 * SQL Sanitizer Service
 *
 * Provides specialized sanitization for SQL query components that cannot
 * be handled by standard parameterized queries (column names, sort directions,
 * LIKE search terms).
 *
 * IMPORTANT: For values in WHERE clauses, always use parameterized queries
 * (prepared statements) instead of this service. This service is for the
 * structural parts of queries that cannot be parameterized.
 *
 * Usage:
 *   $sqlSan = new SqlSanitizer();
 *
 *   // Escape LIKE wildcards in a search term
 *   $safeTerm = $sqlSan->sanitizeSearch($userInput);
 *   $db->where("name LIKE ?", ['%' . $safeTerm . '%']);
 *
 *   // Whitelist sort column
 *   $column = $sqlSan->sanitizeSortColumn($_GET['sort'], ['name', 'created_at', 'price']);
 *
 *   // Whitelist sort direction
 *   $dir = $sqlSan->sanitizeSortDirection($_GET['order']);
 */
class SqlSanitizer
{
    /**
     * Sanitize a search term for use in SQL LIKE clauses.
     *
     * Escapes the LIKE wildcard characters (%, _) so that user input is
     * treated as literal text, not as wildcard patterns.
     * The result should be used with parameterized queries.
     *
     * @param string $term Raw search term from user input
     * @return string Escaped search term safe for LIKE queries
     */
    public function sanitizeSearch(string $term): string
    {
        // Trim whitespace
        $term = trim($term);

        // Remove null bytes
        $term = str_replace("\0", '', $term);

        // Escape LIKE wildcard characters so they are treated literally
        $term = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $term);

        return $term;
    }

    /**
     * Sanitize a sort column name by checking it against a whitelist.
     *
     * Column names cannot be parameterized in prepared statements, so we
     * must validate them against an explicit whitelist of allowed column names.
     *
     * @param string $column         The requested sort column from user input
     * @param array  $allowedColumns Whitelist of valid column names
     * @param string $default        Default column to use if input is not in the whitelist
     * @return string A safe column name guaranteed to be in the whitelist
     */
    public function sanitizeSortColumn(string $column, array $allowedColumns, string $default = ''): string
    {
        $column = trim($column);

        if (empty($allowedColumns)) {
            return $default;
        }

        // Use the first allowed column as default if none specified
        if ($default === '') {
            $default = $allowedColumns[0];
        }

        // Strict whitelist check
        if (in_array($column, $allowedColumns, true)) {
            return $column;
        }

        return $default;
    }

    /**
     * Sanitize a sort direction to only allow 'ASC' or 'DESC'.
     *
     * @param string $dir Raw sort direction from user input
     * @param string $default Default direction if input is invalid
     * @return string Either 'ASC' or 'DESC'
     */
    public function sanitizeSortDirection(string $dir, string $default = 'ASC'): string
    {
        $dir = strtoupper(trim($dir));

        if ($dir === 'ASC' || $dir === 'DESC') {
            return $dir;
        }

        return strtoupper($default) === 'DESC' ? 'DESC' : 'ASC';
    }

    /**
     * Sanitize an identifier (table or column name) by removing
     * all characters that are not alphanumeric, underscore, or dot.
     *
     * This is a last-resort sanitizer for dynamic identifiers.
     * Prefer using sanitizeSortColumn() with a whitelist instead.
     *
     * @param string $identifier Raw identifier
     * @return string Sanitized identifier
     */
    public function sanitizeIdentifier(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_.]/', '', $identifier);
    }
}
