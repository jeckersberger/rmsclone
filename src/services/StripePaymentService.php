<?php
/**
 * StripePaymentService - Stripe/PayPal Zahlungslinks
 *
 * Generiert Zahlungslinks fuer Rechnungen:
 * - Stripe Checkout Sessions
 * - PayPal Payment Links
 *
 * Konfiguration:
 * STRIPE_SECRET_KEY=sk_...
 * STRIPE_WEBHOOK_SECRET=whsec_...
 * PAYPAL_CLIENT_ID=...
 * PAYPAL_CLIENT_SECRET=...
 * PAYPAL_MODE=sandbox|live
 */
class StripePaymentService
{
    private $db;
    private $stripeKey;
    private $paypalClientId;
    private $paypalSecret;
    private $paypalMode;
    private $baseUrl;

    public function __construct($db)
    {
        $this->db = $db;
        $this->stripeKey = getenv('STRIPE_SECRET_KEY') ?: '';
        $this->paypalClientId = getenv('PAYPAL_CLIENT_ID') ?: '';
        $this->paypalSecret = getenv('PAYPAL_CLIENT_SECRET') ?: '';
        $this->paypalMode = getenv('PAYPAL_MODE') ?: 'sandbox';
        $this->baseUrl = rtrim(getenv('ROOT_URL') ?: '', '/');
    }

    /**
     * Stripe Checkout Session erstellen
     */
    public function createStripeCheckout(int $documentId, float $amountEur, string $description, string $customerEmail = ''): array
    {
        if (empty($this->stripeKey)) {
            return ['success' => false, 'error' => 'Stripe nicht konfiguriert (STRIPE_SECRET_KEY)'];
        }

        $amountCents = (int) round($amountEur * 100);

        $params = [
            'payment_method_types' => ['card', 'sepa_debit', 'giropay'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $amountCents,
                    'product_data' => ['name' => $description],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $this->baseUrl . '/payment/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->baseUrl . '/payment/cancel.php',
            'metadata' => ['document_id' => $documentId],
        ];

        if ($customerEmail) {
            $params['customer_email'] = $customerEmail;
        }

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($this->flattenArray($params)),
            CURLOPT_USERPWD => $this->stripeKey . ':',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['url'])) {
            // Link speichern
            $this->db->insert('payment_links', [
                'document_id' => $documentId,
                'provider' => 'stripe',
                'session_id' => $data['id'],
                'payment_url' => $data['url'],
                'amount' => $amountEur,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'url' => $data['url'], 'session_id' => $data['id']];
        }

        return ['success' => false, 'error' => $data['error']['message'] ?? "HTTP {$httpCode}"];
    }

    /**
     * PayPal Payment Link erstellen
     */
    public function createPayPalLink(int $documentId, float $amountEur, string $description): array
    {
        if (empty($this->paypalClientId)) {
            return ['success' => false, 'error' => 'PayPal nicht konfiguriert (PAYPAL_CLIENT_ID)'];
        }

        // 1. Access Token holen
        $token = $this->getPayPalAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'PayPal-Authentifizierung fehlgeschlagen'];
        }

        // 2. Order erstellen
        $apiBase = $this->paypalMode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $ch = curl_init($apiBase . '/v2/checkout/orders');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'description' => $description,
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value' => number_format($amountEur, 2, '.', ''),
                    ],
                    'custom_id' => (string) $documentId,
                ]],
                'application_context' => [
                    'return_url' => $this->baseUrl . '/payment/success.php?provider=paypal',
                    'cancel_url' => $this->baseUrl . '/payment/cancel.php',
                ],
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 201 && isset($data['id'])) {
            $approvalUrl = '';
            foreach ($data['links'] ?? [] as $link) {
                if ($link['rel'] === 'approve') {
                    $approvalUrl = $link['href'];
                    break;
                }
            }

            $this->db->insert('payment_links', [
                'document_id' => $documentId,
                'provider' => 'paypal',
                'session_id' => $data['id'],
                'payment_url' => $approvalUrl,
                'amount' => $amountEur,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'url' => $approvalUrl, 'order_id' => $data['id']];
        }

        return ['success' => false, 'error' => $data['message'] ?? "HTTP {$httpCode}"];
    }

    /**
     * Zahlungsstatus pruefen
     */
    public function checkPaymentStatus(string $sessionId, string $provider = 'stripe'): array
    {
        $this->db->where('session_id', $sessionId);
        $link = $this->db->getOne('payment_links');
        if (!$link) return ['status' => 'unknown'];

        return [
            'status' => $link['status'],
            'document_id' => $link['document_id'],
            'amount' => floatval($link['amount']),
            'provider' => $link['provider'],
        ];
    }

    /**
     * Stripe Webhook verarbeiten (Zahlung bestaetigt)
     */
    public function handleStripeWebhook(string $payload, string $sigHeader): array
    {
        $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
        if (empty($webhookSecret)) {
            return ['success' => false, 'error' => 'Webhook-Secret nicht konfiguriert'];
        }

        // Signatur pruefen
        $timestamp = '';
        $signature = '';
        foreach (explode(',', $sigHeader) as $part) {
            [$key, $value] = explode('=', $part, 2);
            if ($key === 't') $timestamp = $value;
            if ($key === 'v1') $signature = $value;
        }

        $expectedSig = hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret);
        if (!hash_equals($expectedSig, $signature)) {
            return ['success' => false, 'error' => 'Ungueltige Signatur'];
        }

        $event = json_decode($payload, true);
        if (($event['type'] ?? '') === 'checkout.session.completed') {
            $sessionId = $event['data']['object']['id'] ?? '';
            $this->db->where('session_id', $sessionId);
            $this->db->update('payment_links', [
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
            ]);
            return ['success' => true, 'session_id' => $sessionId];
        }

        return ['success' => true, 'ignored' => true];
    }

    private function getPayPalAccessToken(): ?string
    {
        $apiBase = $this->paypalMode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $ch = curl_init($apiBase . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $this->paypalClientId . ':' . $this->paypalSecret,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}[{$key}]" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }
}
