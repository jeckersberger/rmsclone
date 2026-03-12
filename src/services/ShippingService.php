<?php
/**
 * ShippingService - Versand-Integration (DHL, DPD)
 *
 * Erstellt Versandlabels und verfolgt Sendungen ueber die APIs
 * von DHL und DPD. Unterstuetzt Paket- und Stueckgutversand.
 *
 * Konfiguration:
 * DHL_API_KEY=...
 * DHL_API_SECRET=...
 * DHL_ACCOUNT_NUMBER=...
 * DPD_DELIS_ID=...
 * DPD_PASSWORD=...
 * SHIPPING_SENDER_NAME=...
 * SHIPPING_SENDER_STREET=...
 * SHIPPING_SENDER_ZIP=...
 * SHIPPING_SENDER_CITY=...
 */
class ShippingService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Versandlabel erstellen
     */
    public function createShipment(string $provider, array $recipient, array $parcel, int $projectId = 0): array
    {
        $provider = strtolower($provider);

        $result = match ($provider) {
            'dhl' => $this->createDhlShipment($recipient, $parcel),
            'dpd' => $this->createDpdShipment($recipient, $parcel),
            default => ['success' => false, 'error' => 'Unbekannter Provider: ' . $provider],
        };

        if ($result['success']) {
            $this->db->insert('shipments', [
                'provider' => $provider,
                'tracking_number' => $result['tracking_number'] ?? '',
                'label_url' => $result['label_url'] ?? '',
                'projects_id' => $projectId,
                'recipient_name' => $recipient['name'] ?? '',
                'recipient_address' => ($recipient['street'] ?? '') . ', ' . ($recipient['zip'] ?? '') . ' ' . ($recipient['city'] ?? ''),
                'weight_kg' => floatval($parcel['weight'] ?? 0),
                'status' => 'created',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $result;
    }

    /**
     * Sendungsverfolgung
     */
    public function trackShipment(string $trackingNumber, string $provider = 'dhl'): array
    {
        return match ($provider) {
            'dhl' => $this->trackDhl($trackingNumber),
            'dpd' => $this->trackDpd($trackingNumber),
            default => ['success' => false, 'error' => 'Unbekannter Provider'],
        };
    }

    /**
     * Alle Sendungen eines Projekts
     */
    public function getProjectShipments(int $projectId): array
    {
        $this->db->where('projects_id', $projectId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('shipments') ?: [];
    }

    /**
     * DHL Geschaeftskundenversand API
     */
    private function createDhlShipment(array $recipient, array $parcel): array
    {
        $apiKey = getenv('DHL_API_KEY') ?: '';
        $apiSecret = getenv('DHL_API_SECRET') ?: '';
        $accountNumber = getenv('DHL_ACCOUNT_NUMBER') ?: '';

        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'DHL nicht konfiguriert (DHL_API_KEY)'];
        }

        $sender = $this->getSenderAddress();

        $shipmentData = [
            'profile' => 'STANDARD_GRUPPENPROFIL',
            'shipments' => [[
                'product' => 'V01PAK', // DHL Paket
                'billingNumber' => $accountNumber,
                'shipper' => [
                    'name1' => $sender['name'],
                    'addressStreet' => $sender['street'],
                    'postalCode' => $sender['zip'],
                    'city' => $sender['city'],
                    'country' => 'DEU',
                ],
                'consignee' => [
                    'name1' => $recipient['name'] ?? '',
                    'addressStreet' => $recipient['street'] ?? '',
                    'postalCode' => $recipient['zip'] ?? '',
                    'city' => $recipient['city'] ?? '',
                    'country' => $recipient['country'] ?? 'DEU',
                ],
                'details' => [
                    'weight' => ['uom' => 'kg', 'value' => floatval($parcel['weight'] ?? 5)],
                    'dim' => [
                        'uom' => 'cm',
                        'length' => intval($parcel['length'] ?? 60),
                        'width' => intval($parcel['width'] ?? 40),
                        'height' => intval($parcel['height'] ?? 30),
                    ],
                ],
            ]],
        ];

        $ch = curl_init('https://api-eu.dhl.com/parcel/de/shipping/v2/orders');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($shipmentData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'dhl-api-key: ' . $apiKey,
            ],
            CURLOPT_USERPWD => $apiKey . ':' . $apiSecret,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['items'][0])) {
            $item = $data['items'][0];
            return [
                'success' => true,
                'tracking_number' => $item['shipmentNo'] ?? '',
                'label_url' => $item['label']['url'] ?? '',
                'provider' => 'dhl',
            ];
        }

        return ['success' => false, 'error' => $data['detail'] ?? "DHL API HTTP {$httpCode}"];
    }

    /**
     * DPD Versand API
     */
    private function createDpdShipment(array $recipient, array $parcel): array
    {
        $delisId = getenv('DPD_DELIS_ID') ?: '';
        $password = getenv('DPD_PASSWORD') ?: '';

        if (empty($delisId)) {
            return ['success' => false, 'error' => 'DPD nicht konfiguriert (DPD_DELIS_ID)'];
        }

        $sender = $this->getSenderAddress();

        // DPD Cloud API
        $ch = curl_init('https://cloud.dpd.com/api/v1/shipments');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'sender' => [
                    'name1' => $sender['name'],
                    'street' => $sender['street'],
                    'zipCode' => $sender['zip'],
                    'city' => $sender['city'],
                    'country' => 'DE',
                ],
                'recipient' => [
                    'name1' => $recipient['name'] ?? '',
                    'street' => $recipient['street'] ?? '',
                    'zipCode' => $recipient['zip'] ?? '',
                    'city' => $recipient['city'] ?? '',
                    'country' => $recipient['country'] ?? 'DE',
                ],
                'parcels' => [[
                    'weight' => floatval($parcel['weight'] ?? 5) * 100, // in 10g
                ]],
                'product' => 'CL',
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($delisId . ':' . $password),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['shipmentResponses'][0])) {
            $resp = $data['shipmentResponses'][0];
            return [
                'success' => true,
                'tracking_number' => $resp['parcelInformation'][0]['parcelLabelNumber'] ?? '',
                'label_url' => $resp['parcelInformation'][0]['output'][0]['content'] ?? '',
                'provider' => 'dpd',
            ];
        }

        return ['success' => false, 'error' => "DPD API HTTP {$httpCode}"];
    }

    private function trackDhl(string $trackingNumber): array
    {
        $apiKey = getenv('DHL_API_KEY') ?: '';
        if (empty($apiKey)) return ['success' => false, 'error' => 'DHL nicht konfiguriert'];

        $ch = curl_init('https://api-eu.dhl.com/track/shipments?trackingNumber=' . urlencode($trackingNumber));
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['dhl-api-key: ' . $apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $events = $data['shipments'][0]['events'] ?? [];

        return [
            'success' => true,
            'status' => $data['shipments'][0]['status']['statusCode'] ?? 'unknown',
            'events' => array_map(fn($e) => [
                'timestamp' => $e['timestamp'] ?? '',
                'description' => $e['description'] ?? '',
                'location' => $e['location']['address']['addressLocality'] ?? '',
            ], array_slice($events, 0, 10)),
        ];
    }

    private function trackDpd(string $trackingNumber): array
    {
        return ['success' => true, 'status' => 'tracking_url', 'url' => 'https://tracking.dpd.de/parcelstatus?query=' . urlencode($trackingNumber)];
    }

    private function getSenderAddress(): array
    {
        return [
            'name' => getenv('SHIPPING_SENDER_NAME') ?: 'Firma',
            'street' => getenv('SHIPPING_SENDER_STREET') ?: '',
            'zip' => getenv('SHIPPING_SENDER_ZIP') ?: '',
            'city' => getenv('SHIPPING_SENDER_CITY') ?: '',
        ];
    }
}
