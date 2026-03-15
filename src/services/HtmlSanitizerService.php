<?php

/**
 * Server-seitiger HTML-Sanitizer fuer WYSIWYG/Summernote-Content.
 *
 * Entfernt gefaehrliche Tags und Attribute BEVOR der Content
 * in die Datenbank geschrieben wird. Damit sind alle Twig-Templates
 * die `|raw` verwenden automatisch geschuetzt.
 *
 * Erlaubte Tags: Basales HTML fuer Rich-Text (Formatting, Listen, Links, Bilder, Tabellen)
 * Verboten: script, iframe, object, embed, form, input, style (als Tag), on*-Attribute
 */
class HtmlSanitizerService
{
    /**
     * Erlaubte HTML-Tags fuer Rich-Text-Content
     */
    private static array $allowedTags = [
        'p', 'br', 'b', 'i', 'u', 'strong', 'em', 'strike', 's', 'del',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'blockquote', 'pre', 'code',
        'div', 'span', 'hr', 'sub', 'sup',
    ];

    /**
     * Erlaubte Attribute pro Tag
     */
    private static array $allowedAttributes = [
        '*' => ['class', 'style', 'id', 'title'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        'table' => ['border', 'cellpadding', 'cellspacing'],
    ];

    /**
     * Erlaubte URL-Protokolle fuer href und src
     */
    private static array $allowedProtocols = ['http', 'https', 'mailto', 'tel'];

    /**
     * Gefaehrliche CSS-Properties die in style-Attributen entfernt werden
     */
    private static array $dangerousCssPatterns = [
        '/expression\s*\(/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/-moz-binding/i',
        '/behavior\s*:/i',
        '/url\s*\(\s*["\']?\s*javascript/i',
    ];

    /**
     * HTML-Content sanitizen.
     *
     * @param string|null $html  Roher HTML-String (z.B. von Summernote)
     * @return string            Gereinigter HTML-String
     */
    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // 1. Alle nicht-erlaubten Tags entfernen
        $allowedTagString = '<' . implode('><', self::$allowedTags) . '>';
        $html = strip_tags($html, $allowedTagString);

        // 2. Gefaehrliche Attribute entfernen (on*-Events, data-URIs in src, javascript: in href)
        $html = self::removeEventHandlers($html);
        $html = self::sanitizeUrls($html);
        $html = self::sanitizeStyles($html);

        return $html;
    }

    /**
     * Entfernt alle on*-Event-Handler-Attribute (onclick, onerror, onload, etc.)
     */
    private static function removeEventHandlers(string $html): string
    {
        // Entferne on*="..." und on*='...' Attribute
        return preg_replace(
            '/\s+on\w+\s*=\s*(["\']).*?\1/is',
            '',
            $html
        );
    }

    /**
     * Sanitized href und src Attribute - nur erlaubte Protokolle.
     */
    private static function sanitizeUrls(string $html): string
    {
        // href-Attribute pruefen
        $html = preg_replace_callback(
            '/(<a\s[^>]*?)href\s*=\s*(["\'])(.*?)\2/is',
            function ($matches) {
                $url = trim($matches[3]);
                if (self::isUrlSafe($url)) {
                    return $matches[0]; // URL ist sicher, beibehalten
                }
                // Unsichere URL entfernen, Tag bleibt aber ohne href
                return $matches[1] . 'href="#"';
            },
            $html
        );

        // src-Attribute pruefen (fuer img)
        $html = preg_replace_callback(
            '/(<img\s[^>]*?)src\s*=\s*(["\'])(.*?)\2/is',
            function ($matches) {
                $url = trim($matches[3]);
                if (self::isUrlSafe($url) || self::isDataImageSafe($url)) {
                    return $matches[0];
                }
                return $matches[1] . 'src=""';
            },
            $html
        );

        return $html;
    }

    /**
     * Sanitized style-Attribute gegen CSS-Injection.
     */
    private static function sanitizeStyles(string $html): string
    {
        return preg_replace_callback(
            '/style\s*=\s*(["\'])(.*?)\1/is',
            function ($matches) {
                $style = $matches[2];
                foreach (self::$dangerousCssPatterns as $pattern) {
                    if (preg_match($pattern, $style)) {
                        return ''; // Gesamtes style-Attribut entfernen
                    }
                }
                return $matches[0]; // Style ist sicher
            },
            $html
        );
    }

    /**
     * Prueft ob eine URL ein erlaubtes Protokoll verwendet.
     */
    private static function isUrlSafe(string $url): bool
    {
        // Relative URLs sind sicher
        if (strpos($url, '/') === 0 || strpos($url, '#') === 0 || strpos($url, '?') === 0) {
            return true;
        }
        // Protokoll pruefen
        $protocol = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if ($protocol === '') {
            return true; // Kein Protokoll = relativ
        }
        return in_array($protocol, self::$allowedProtocols);
    }

    /**
     * Prueft ob eine data: URL ein sicheres Bild ist.
     */
    private static function isDataImageSafe(string $url): bool
    {
        return (bool)preg_match('/^data:image\/(png|jpe?g|gif|webp|svg\+xml);base64,/i', $url);
    }
}
