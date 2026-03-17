<?php
/**
 * VIES USt-IdNr. Validierung
 *
 * Prueft europaeische USt-IdNr. ueber den VIES SOAP-Dienst
 * der Europaeischen Kommission und cached die Ergebnisse.
 *
 * WSDL: https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl
 */
class ViesValidationService
{
    private $db;

    /** VIES SOAP WSDL URL */
    private const VIES_WSDL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    /** Cache-Dauer in Stunden */
    private const CACHE_HOURS = 24;

    /** Gueltige EU-Laendercodes */
    private const EU_COUNTRY_CODES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES',
        'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ═══════════════════════════════════════════════
    //  VALIDIERUNG
    // ═══════════════════════════════════════════════

    /**
     * USt-IdNr. ueber VIES validieren
     *
     * @param string      $vatId       USt-IdNr. (z.B. "DE123456789")
     * @param string|null $countryCode Laendercode (wird aus vatId extrahiert falls leer)
     * @return array ['valid' => bool, 'name' => string, 'address' => string, 'requestDate' => string, 'error' => string|null]
     */
    public function validateVatId(string $vatId, ?string $countryCode = null): array
    {
        $vatId = $this->formatVatId($vatId);

        if (empty($vatId) || strlen($vatId) < 4) {
            return [
                'valid'       => false,
                'name'        => '',
                'address'     => '',
                'requestDate' => date('Y-m-d H:i:s'),
                'error'       => 'USt-IdNr. ist zu kurz oder leer.',
            ];
        }

        // Laendercode extrahieren
        if ($countryCode === null) {
            $countryCode = strtoupper(substr($vatId, 0, 2));
        }
        $countryCode = strtoupper(trim($countryCode));

        // Laendercode validieren
        if (!in_array($countryCode, self::EU_COUNTRY_CODES)) {
            return [
                'valid'       => false,
                'name'        => '',
                'address'     => '',
                'requestDate' => date('Y-m-d H:i:s'),
                'error'       => 'Ungueltiger EU-Laendercode: ' . $countryCode,
            ];
        }

        // Nummer ohne Laendercode
        $vatNumber = substr($vatId, 2);

        // Cache pruefen
        $cached = $this->getCachedResult($vatId, $countryCode);
        if ($cached !== null) {
            return [
                'valid'       => (bool)$cached['is_valid'],
                'name'        => $cached['company_name'] ?? '',
                'address'     => $cached['company_address'] ?? '',
                'requestDate' => $cached['validated_at'],
                'error'       => null,
                'cached'      => true,
            ];
        }

        // VIES SOAP-Abfrage
        try {
            $client = new SoapClient(self::VIES_WSDL, [
                'connection_timeout' => 10,
                'exceptions'         => true,
            ]);

            $response = $client->checkVat([
                'countryCode' => $countryCode,
                'vatNumber'   => $vatNumber,
            ]);

            $result = [
                'valid'       => (bool)$response->valid,
                'name'        => trim($response->name ?? ''),
                'address'     => trim($response->address ?? ''),
                'requestDate' => $response->requestDate ?? date('Y-m-d'),
                'error'       => null,
                'cached'      => false,
            ];

            // Ergebnis cachen
            $this->cacheResult($vatId, $countryCode, $result);

            return $result;
        } catch (\SoapFault $e) {
            return [
                'valid'       => false,
                'name'        => '',
                'address'     => '',
                'requestDate' => date('Y-m-d H:i:s'),
                'error'       => 'VIES-Dienst nicht erreichbar: ' . $e->getMessage(),
                'cached'      => false,
            ];
        } catch (\Exception $e) {
            return [
                'valid'       => false,
                'name'        => '',
                'address'     => '',
                'requestDate' => date('Y-m-d H:i:s'),
                'error'       => 'Fehler bei der Validierung: ' . $e->getMessage(),
                'cached'      => false,
            ];
        }
    }

    /**
     * USt-IdNr. normalisieren (Leerzeichen entfernen, Grossbuchstaben)
     */
    public function formatVatId(string $vatId): string
    {
        // Leerzeichen, Punkte, Bindestriche entfernen
        $vatId = preg_replace('/[\s.\-]/', '', $vatId);
        return strtoupper($vatId);
    }

    /**
     * Validierungshistorie fuer einen Kunden abrufen
     *
     * @param int $clientId Kunden-ID
     * @return array Liste der Validierungsergebnisse
     */
    public function getValidationHistory(int $clientId): array
    {
        // Kunden-USt-IdNr. laden
        $this->db->where('clients_id', $clientId);
        $client = $this->db->getOne('clients', null, ['clients_vatId']);

        if (!$client || empty($client['clients_vatId'])) {
            return [];
        }

        $vatId = $this->formatVatId($client['clients_vatId']);

        $this->db->where('vat_id', $vatId);
        $this->db->orderBy('validated_at', 'DESC');
        return $this->db->get('vies_validation_cache') ?: [];
    }

    // ═══════════════════════════════════════════════
    //  CACHE
    // ═══════════════════════════════════════════════

    /**
     * Gecachtes Ergebnis abrufen (falls noch gueltig)
     */
    private function getCachedResult(string $vatId, string $countryCode): ?array
    {
        $this->db->where('vat_id', $vatId);
        $this->db->where('country_code', $countryCode);
        $this->db->where('expires_at', date('Y-m-d H:i:s'), '>');
        $this->db->orderBy('validated_at', 'DESC');
        $result = $this->db->getOne('vies_validation_cache', null);

        return $result ?: null;
    }

    /**
     * Validierungsergebnis cachen
     */
    private function cacheResult(string $vatId, string $countryCode, array $result): void
    {
        $this->db->insert('vies_validation_cache', [
            'vat_id'          => $vatId,
            'country_code'    => $countryCode,
            'is_valid'        => $result['valid'] ? 1 : 0,
            'company_name'    => $result['name'] ?: null,
            'company_address' => $result['address'] ?: null,
            'validated_at'    => date('Y-m-d H:i:s'),
            'expires_at'      => date('Y-m-d H:i:s', strtotime('+' . self::CACHE_HOURS . ' hours')),
        ]);
    }
}
