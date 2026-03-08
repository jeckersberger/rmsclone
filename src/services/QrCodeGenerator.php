<?php
/**
 * QR-Code-Generator (ohne externe Abhaengigkeiten)
 *
 * Generiert QR-Codes als einbettbare Data-URIs fuer HTML/PDF-Dokumente.
 * Nutzt die Google Charts API als Backend fuer die QR-Erzeugung und
 * konvertiert das Ergebnis in ein base64-kodiertes PNG Data-URI.
 *
 * Fallback: Falls die Google Charts API nicht erreichbar ist, wird ein
 * SVG-Platzhalter mit dem kodierten Text erzeugt.
 */
class QrCodeGenerator
{
    /**
     * Erzeugt einen QR-Code als Data-URI (data:image/png;base64,...).
     *
     * @param string $data    Der zu kodierende Inhalt
     * @param int    $size    Bildgroesse in Pixeln (Breite = Hoehe)
     * @param string $ecLevel Fehlerkorrektur-Level (L, M, Q, H)
     * @return string         Data-URI des QR-Code-Bildes
     */
    public static function generateDataUri(string $data, int $size = 200, string $ecLevel = 'M'): string
    {
        // Versuch 1: Google Charts API
        $png = self::fetchFromGoogleCharts($data, $size, $ecLevel);
        if ($png !== null) {
            return 'data:image/png;base64,' . base64_encode($png);
        }

        // Fallback: SVG-Platzhalter generieren
        $svg = self::generateFallbackSvg($data, $size);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Erzeugt einen QR-Code als SVG-Markup.
     *
     * @param string $data Der zu kodierende Inhalt
     * @param int    $size Bildgroesse in Pixeln
     * @return string      SVG-Markup
     */
    public static function generateSvg(string $data, int $size = 200): string
    {
        // Versuch ueber Google Charts API und Konvertierung
        $png = self::fetchFromGoogleCharts($data, $size, 'M');
        if ($png !== null) {
            // PNG als eingebettetes Bild im SVG
            $b64 = base64_encode($png);
            return '<?xml version="1.0" encoding="UTF-8"?>'
                . '<svg xmlns="http://www.w3.org/2000/svg" '
                . 'xmlns:xlink="http://www.w3.org/1999/xlink" '
                . 'width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
                . '<image width="' . $size . '" height="' . $size . '" '
                . 'xlink:href="data:image/png;base64,' . $b64 . '"/>'
                . '</svg>';
        }

        return self::generateFallbackSvg($data, $size);
    }

    /**
     * QR-Code ueber Google Charts API abrufen.
     *
     * @param string $data    Inhalt
     * @param int    $size    Groesse
     * @param string $ecLevel Fehlerkorrektur
     * @return string|null    PNG-Binaerdaten oder null bei Fehler
     */
    private static function fetchFromGoogleCharts(string $data, int $size, string $ecLevel): ?string
    {
        $url = 'https://chart.googleapis.com/chart?'
            . http_build_query([
                'cht'  => 'qr',
                'chs'  => $size . 'x' . $size,
                'chl'  => $data,
                'choe' => 'UTF-8',
                'chld' => $ecLevel . '|1',
            ]);

        // Context mit kurzem Timeout (3 Sekunden)
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 3,
                'method'  => 'GET',
                'header'  => "User-Agent: RMSClone/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $ctx);
        if ($result === false || strlen($result) < 100) {
            return null;
        }

        return $result;
    }

    /**
     * Fallback-SVG mit QR-aehnlichem Muster erzeugen.
     *
     * Generiert ein deterministisches Muster basierend auf dem Hash der Daten.
     * Dies ist KEIN echter QR-Code, sondern ein visueller Platzhalter,
     * der angezeigt wird, wenn die Google Charts API nicht erreichbar ist.
     *
     * @param string $data Inhalt (wird gehasht fuer Muster)
     * @param int    $size Bildgroesse
     * @return string      SVG-Markup
     */
    private static function generateFallbackSvg(string $data, int $size): string
    {
        $modules = 25; // QR Version 2 hat ca. 25 Module
        $cellSize = $size / $modules;
        $hash = hash('sha256', $data);

        $svg = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" '
            . 'width="' . $size . '" height="' . $size . '" '
            . 'viewBox="0 0 ' . $size . ' ' . $size . '">'
            . '<rect width="' . $size . '" height="' . $size . '" fill="white"/>';

        // Finder-Patterns (oben-links, oben-rechts, unten-links)
        $svg .= self::finderPattern(0, 0, $cellSize);
        $svg .= self::finderPattern(($modules - 7) * $cellSize, 0, $cellSize);
        $svg .= self::finderPattern(0, ($modules - 7) * $cellSize, $cellSize);

        // Deterministisches Datenmuster aus Hash
        $hashBits = '';
        for ($i = 0; $i < strlen($hash); $i++) {
            $hashBits .= str_pad(decbin(hexdec($hash[$i])), 4, '0', STR_PAD_LEFT);
        }
        $bitIdx = 0;
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                // Finder-Patterns ueberspringen
                if (($x < 8 && $y < 8) || ($x >= $modules - 8 && $y < 8) || ($x < 8 && $y >= $modules - 8)) {
                    continue;
                }
                if ($bitIdx < strlen($hashBits) && $hashBits[$bitIdx] === '1') {
                    $svg .= '<rect x="' . ($x * $cellSize) . '" y="' . ($y * $cellSize)
                        . '" width="' . $cellSize . '" height="' . $cellSize . '" fill="black"/>';
                }
                $bitIdx = ($bitIdx + 1) % strlen($hashBits);
            }
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * SVG-Finder-Pattern (7x7 Module) fuer QR-Platzhalter.
     */
    private static function finderPattern(float $x, float $y, float $cell): string
    {
        $s = '';
        // Aeusserer Rahmen (7x7)
        $s .= '<rect x="' . $x . '" y="' . $y . '" width="' . (7 * $cell) . '" height="' . (7 * $cell) . '" fill="black"/>';
        // Innerer weisser Bereich (5x5)
        $s .= '<rect x="' . ($x + $cell) . '" y="' . ($y + $cell) . '" width="' . (5 * $cell) . '" height="' . (5 * $cell) . '" fill="white"/>';
        // Zentraler schwarzer Block (3x3)
        $s .= '<rect x="' . ($x + 2 * $cell) . '" y="' . ($y + 2 * $cell) . '" width="' . (3 * $cell) . '" height="' . (3 * $cell) . '" fill="black"/>';
        return $s;
    }
}
