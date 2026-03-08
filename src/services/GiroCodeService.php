<?php
/**
 * GiroCode / EPC-QR-Code Service
 *
 * Generiert EPC-konforme QR-Codes (European Payments Council, EPC069-12)
 * fuer Rechnungen. Der QR-Code kann mit gaengigen Banking-Apps gescannt
 * werden, um eine SEPA-Ueberweisung vorzubereiten.
 *
 * Format: EPC069-12 v002
 * @see https://www.europeanpaymentscouncil.eu/document-library/guidance-documents/quick-response-code-guidelines
 */

require_once __DIR__ . '/QrCodeGenerator.php';

class GiroCodeService
{
    /**
     * Erzeugt einen EPC/GiroCode QR-Code als Data-URI.
     *
     * @param string $iban          IBAN des Zahlungsempfaengers (ohne Leerzeichen)
     * @param string $bic           BIC/SWIFT des Zahlungsempfaengers
     * @param string $recipientName Name des Zahlungsempfaengers (max. 70 Zeichen)
     * @param float  $amount        Betrag in EUR (0.01 - 999999999.99)
     * @param string $reference     Verwendungszweck / Referenz (max. 140 Zeichen)
     * @param int    $size          QR-Code-Groesse in Pixeln
     * @return string|null          Data-URI des QR-Code-Bildes oder null bei fehlenden Daten
     */
    public static function generateGiroCode(
        string $iban,
        string $bic,
        string $recipientName,
        float $amount,
        string $reference,
        int $size = 200
    ): ?string {
        // Validierung: Pflichtfelder pruefen
        $iban = preg_replace('/\s+/', '', $iban);
        if (empty($iban) || $amount <= 0) {
            return null;
        }

        // BIC kann leer sein (seit SEPA 2016 fuer Inlandszahlungen optional)
        $bic = preg_replace('/\s+/', '', $bic);

        // Empfaengername kuerzen (max. 70 Zeichen lt. EPC-Standard)
        $recipientName = mb_substr(trim($recipientName), 0, 70);

        // Betrag formatieren (max. 2 Dezimalstellen, Punkt als Trenner)
        $amountFormatted = 'EUR' . number_format($amount, 2, '.', '');

        // Verwendungszweck kuerzen (max. 140 Zeichen)
        $reference = mb_substr(trim($reference), 0, 140);

        // EPC069-12 QR-Code-Inhalt aufbauen (zeilenbasiert)
        // Zeile 1: Service Tag (immer "BCD")
        // Zeile 2: Version (002)
        // Zeile 3: Zeichensatz (1 = UTF-8)
        // Zeile 4: Identifikation (SCT = SEPA Credit Transfer)
        // Zeile 5: BIC des Empfaengers
        // Zeile 6: Name des Empfaengers
        // Zeile 7: IBAN des Empfaengers
        // Zeile 8: Betrag (EURxx.xx)
        // Zeile 9: Zweck (leer)
        // Zeile 10: Strukturierte Referenz (leer - wir nutzen unstrukturiert)
        // Zeile 11: Unstrukturierter Verwendungszweck
        $epcData = implode("\n", [
            'BCD',              // Service Tag
            '002',              // Version
            '1',                // Zeichensatz (UTF-8)
            'SCT',              // SEPA Credit Transfer
            $bic,               // BIC
            $recipientName,     // Empfaenger
            $iban,              // IBAN
            $amountFormatted,   // Betrag
            '',                 // Zweck (Purpose)
            '',                 // Strukturierte Referenz
            $reference,         // Unstrukturierter Verwendungszweck
        ]);

        return QrCodeGenerator::generateDataUri($epcData, $size, 'M');
    }

    /**
     * Erzeugt einen GiroCode aus den Business- und Dokumentdaten.
     *
     * Convenience-Methode fuer die Integration im DocumentRenderer.
     *
     * @param array $business  Business/Instanz-Daten
     * @param array $docData   Dokumentdaten (Nummer, Betrag etc.)
     * @param array $totals    Summen-Daten
     * @param int   $size      QR-Code-Groesse
     * @return string|null     Data-URI oder null
     */
    public static function generateFromDocument(array $business, array $docData, array $totals, int $size = 180): ?string
    {
        $iban = $business['instances_bankIban'] ?? '';
        $bic  = $business['instances_bankBic'] ?? '';
        $name = $business['instances_name'] ?? '';
        $amount = (float)($totals['gross'] ?? 0);
        $reference = ($docData['number'] ?? 'Rechnung');

        if (empty($iban) || $amount <= 0) {
            return null;
        }

        return self::generateGiroCode($iban, $bic, $name, $amount, $reference, $size);
    }
}
