<?php

/**
 * AnonymizationService - PII Protection for Cloud AI Requests
 *
 * Anonymizes sensitive data before sending prompts to cloud AI providers.
 * Maintains in-memory replacement map for deAnonymization of responses.
 * CRITICAL: replacementMap is NEVER persisted to database or logs.
 *
 * Modes:
 * - strict: DB entities + all regex patterns + IP addresses
 * - standard: DB entities + most regex patterns (no strict-only patterns)
 * - minimal: DB entities only
 * - off: No anonymization (bypassed for local providers like Ollama)
 */
class AnonymizationService
{
    // In-memory replacement mapping - NEVER persisted!
    // Format: ['[PERSON_0]' => 'John Doe', '[EMAIL_1]' => 'john@example.com', ...]
    private array $replacementMap = [];

    // Counters for audit logging
    private array $counters = [];

    public function __construct(
        private $db,
    ) {
        $this->resetCounters();
    }

    /**
     * Main anonymization method
     *
     * @param string $text Text to anonymize
     * @param string $mode Anonymization mode (strict|standard|minimal|off)
     * @param int $instanceId Instance ID for DB entity lookups
     * @return string Anonymized text with [TYPE_N] placeholders
     */
    public function anonymize(string $text, string $mode = 'strict', int $instanceId = 0): string
    {
        if ($mode === 'off') {
            return $text;
        }

        // Clear previous replacements for this request
        $this->replacementMap = [];
        $this->resetCounters();

        // Step 1: Replace known database entities (all modes)
        $text = $this->replaceKnownEntities($text, $instanceId);

        // Step 2: Replace by regex patterns (varies by mode)
        $text = $this->replaceByRegex($text, $mode);

        return $text;
    }

    /**
     * Reverse anonymization - restore placeholders to original values
     *
     * @param string $text Text with [TYPE_N] placeholders
     * @return string Text with original values restored
     */
    public function deAnonymize(string $text): string
    {
        // Replace all placeholders with original values
        foreach ($this->replacementMap as $placeholder => $original) {
            $text = str_replace($placeholder, $original, $text);
        }

        return $text;
    }

    /**
     * Get audit log data - NEVER includes actual PII
     *
     * Returns only:
     * - Number of replacements per type
     * - Total replacement count
     * - Character counts (not actual data)
     *
     * @return array Audit-safe summary
     */
    public function getRedactedAuditLog(): array
    {
        return [
            'replacements_count' => count($this->replacementMap),
            'replacement_types' => $this->counters,
            'timestamp' => date('c'),
        ];
    }

    /**
     * Get number of replacements made
     *
     * @return int
     */
    public function getReplacementCount(): int
    {
        return count($this->replacementMap);
    }

    /**
     * Get breakdown of replacement types
     *
     * @return array Format: ['PERSON' => 3, 'EMAIL' => 2, 'IBAN' => 1]
     */
    public function getReplacementTypes(): array
    {
        return $this->counters;
    }

    /**
     * Replace known entities from database
     *
     * Checks clients, contacts, users tables for names and email addresses
     * to remove company-specific identifying information.
     *
     * @param string $text
     * @param int $instanceId
     * @return string
     */
    private function replaceKnownEntities(string $text, int $instanceId): string
    {
        if ($instanceId <= 0) {
            return $text;
        }

        // Get client names and contacts
        $this->db->where('instances_id', $instanceId);
        $clients = $this->db->get('clients', null, ['clients_id', 'clients_name', 'clients_company']);

        foreach ($clients as $client) {
            if (!empty($client['clients_name'])) {
                $pattern = preg_quote($client['clients_name'], '/');
                if (preg_match("/{$pattern}/i", $text)) {
                    $text = preg_replace_callback(
                        "/{$pattern}/i",
                        fn($m) => $this->addReplacement('PERSON', $m[0]),
                        $text
                    );
                }
            }
            if (!empty($client['clients_company'])) {
                $pattern = preg_quote($client['clients_company'], '/');
                if (preg_match("/{$pattern}/i", $text)) {
                    $text = preg_replace_callback(
                        "/{$pattern}/i",
                        fn($m) => $this->addReplacement('COMPANY', $m[0]),
                        $text
                    );
                }
            }
        }

        // Get contact names and emails
        $this->db->where('instances_id', $instanceId);
        $contacts = $this->db->get('contacts', null, ['contacts_id', 'contacts_firstname', 'contacts_lastname', 'contacts_email']);

        foreach ($contacts as $contact) {
            // Full name
            if (!empty($contact['contacts_firstname']) && !empty($contact['contacts_lastname'])) {
                $fullName = $contact['contacts_firstname'] . ' ' . $contact['contacts_lastname'];
                $pattern = preg_quote($fullName, '/');
                if (preg_match("/{$pattern}/i", $text)) {
                    $text = preg_replace_callback(
                        "/{$pattern}/i",
                        fn($m) => $this->addReplacement('PERSON', $m[0]),
                        $text
                    );
                }
            }

            // Email
            if (!empty($contact['contacts_email'])) {
                $pattern = preg_quote($contact['contacts_email'], '/');
                if (preg_match("/{$pattern}/i", $text)) {
                    $text = preg_replace_callback(
                        "/{$pattern}/i",
                        fn($m) => $this->addReplacement('EMAIL', $m[0]),
                        $text
                    );
                }
            }
        }

        // Get user names and emails
        $this->db->where('instances_id', $instanceId);
        $users = $this->db->get('users', null, ['userid', 'user_firstname', 'user_lastname', 'user_email']);

        foreach ($users as $user) {
            // Full name
            if (!empty($user['user_firstname']) && !empty($user['user_lastname'])) {
                $fullName = $user['user_firstname'] . ' ' . $user['user_lastname'];
                $pattern = preg_quote($fullName, '/');
                if (preg_match("/{$pattern}/i", $text)) {
                    $text = preg_replace_callback(
                        "/{$pattern}/i",
                        fn($m) => $this->addReplacement('PERSON', $m[0]),
                        $text
                    );
                }
            }

            // Email
            if (!empty($user['user_email'])) {
                $pattern = preg_quote($user['user_email'], '/');
                if (preg_match("/{$pattern}/i", $text)) {
                    $text = preg_replace_callback(
                        "/{$pattern}/i",
                        fn($m) => $this->addReplacement('EMAIL', $m[0]),
                        $text
                    );
                }
            }
        }

        return $text;
    }

    /**
     * Replace patterns based on regex rules
     *
     * Patterns vary by anonymization mode:
     * - All modes: IBAN, Email, Phone, USt-IdNr, Steuernummer
     * - strict mode only: IP addresses, dates
     *
     * @param string $text
     * @param string $mode
     * @return string
     */
    private function replaceByRegex(string $text, string $mode): string
    {
        // IBAN: DE89 3704 0044 0532 0130 00
        $ibanPattern = '/\b[A-Z]{2}\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{0,2}\b/';
        $text = preg_replace_callback(
            $ibanPattern,
            fn($m) => $this->addReplacement('IBAN', $m[0]),
            $text
        );

        // Email: user@example.com
        $emailPattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/';
        $text = preg_replace_callback(
            $emailPattern,
            fn($m) => $this->addReplacement('EMAIL', $m[0]),
            $text
        );

        // German phone numbers: +49 123 456789 or 0123 456789
        $phonePattern = '/\b(?:\+49|0049|0)\s?[\d\s\-\/]{6,14}\b/';
        $text = preg_replace_callback(
            $phonePattern,
            fn($m) => $this->addReplacement('PHONE', $m[0]),
            $text
        );

        // USt-IdNr: DE123456789
        $ustPattern = '/\bDE\s?\d{9}\b/';
        $text = preg_replace_callback(
            $ustPattern,
            fn($m) => $this->addReplacement('UST_IDNR', $m[0]),
            $text
        );

        // Steuernummer: 12/345/67890
        $steuerPattern = '/\b\d{2,3}\/\d{3}\/\d{4,5}\b/';
        $text = preg_replace_callback(
            $steuerPattern,
            fn($m) => $this->addReplacement('STEUERNUMMER', $m[0]),
            $text
        );

        // Strict mode only: IP addresses
        if ($mode === 'strict') {
            $ipPattern = '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/';
            $text = preg_replace_callback(
                $ipPattern,
                fn($m) => $this->addReplacement('IP', $m[0]),
                $text
            );

            // Strict mode only: Dates (various German formats)
            // DD.MM.YYYY
            $datePattern = '/\b\d{1,2}\.\d{1,2}\.\d{4}\b/';
            $text = preg_replace_callback(
                $datePattern,
                fn($m) => $this->addReplacement('DATE', $m[0]),
                $text
            );
        }

        return $text;
    }

    /**
     * Register a replacement and return placeholder
     *
     * Creates [TYPE_N] placeholder and stores mapping.
     *
     * @param string $type Replacement type (PERSON, EMAIL, IBAN, etc.)
     * @param string $original Original value
     * @return string Placeholder string like [PERSON_0]
     */
    private function addReplacement(string $type, string $original): string
    {
        // Increment counter for this type
        if (!isset($this->counters[$type])) {
            $this->counters[$type] = 0;
        }
        $this->counters[$type]++;

        // Create placeholder
        $index = $this->counters[$type] - 1;
        $placeholder = "[{$type}_{$index}]";

        // Store mapping (in-memory only, never logged)
        $this->replacementMap[$placeholder] = $original;

        return $placeholder;
    }

    /**
     * Reset counters for new request
     */
    private function resetCounters(): void
    {
        $this->counters = [];
    }

    /**
     * Clear all state (useful between requests)
     */
    public function clear(): void
    {
        $this->replacementMap = [];
        $this->resetCounters();
    }
}
