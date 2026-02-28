<?php
/**
 * Simple translation helper for AdamRMS.
 *
 * Usage:
 *   $translator = new Translator('de_DE');
 *   echo $translator->t('dashboard');            // "Übersicht"
 *   echo $translator->t('greeting', ['name' => 'Max']); // "Hallo Max"
 *
 * Translation files live in /src/common/libs/i18n/locales/<locale>.php
 * and return a flat key => value array.
 */
class Translator {
    private array $messages = [];
    private string $locale;

    public function __construct(string $locale = 'de_DE') {
        $this->locale = $locale;
        $file = __DIR__ . '/locales/' . $locale . '.php';
        if (file_exists($file)) {
            $this->messages = require $file;
        }
        // Fallback to English if locale file doesn't exist
        if (empty($this->messages) && $locale !== 'en_GB') {
            $fallback = __DIR__ . '/locales/en_GB.php';
            if (file_exists($fallback)) {
                $this->messages = require $fallback;
            }
        }
    }

    /**
     * Translate a key. Returns the key itself if no translation exists.
     * Supports simple placeholder replacement: {{ name }} in strings.
     */
    public function t(string $key, array $params = []): string {
        $text = $this->messages[$key] ?? $key;
        foreach ($params as $k => $v) {
            $text = str_replace('{{ ' . $k . ' }}', (string)$v, $text);
        }
        return $text;
    }

    public function getLocale(): string {
        return $this->locale;
    }

    public function getAllMessages(): array {
        return $this->messages;
    }
}
