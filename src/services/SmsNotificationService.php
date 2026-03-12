<?php
/**
 * SmsNotificationService - WhatsApp/SMS Benachrichtigungen
 *
 * Unterstuetzt verschiedene Provider:
 * - Twilio (SMS + WhatsApp)
 * - MessageBird
 * - Vonage/Nexmo
 *
 * Konfiguration ueber Umgebungsvariablen:
 * SMS_PROVIDER=twilio|messagebird|vonage
 * SMS_API_KEY=...
 * SMS_API_SECRET=...
 * SMS_FROM=+491234567890
 * WHATSAPP_ENABLED=true
 */
class SmsNotificationService
{
    private $db;
    private $provider;
    private $apiKey;
    private $apiSecret;
    private $fromNumber;
    private $whatsappEnabled;

    public function __construct($db)
    {
        $this->db = $db;
        $this->provider = getenv('SMS_PROVIDER') ?: 'twilio';
        $this->apiKey = getenv('SMS_API_KEY') ?: '';
        $this->apiSecret = getenv('SMS_API_SECRET') ?: '';
        $this->fromNumber = getenv('SMS_FROM') ?: '';
        $this->whatsappEnabled = filter_var(getenv('WHATSAPP_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * SMS senden
     */
    public function sendSms(string $to, string $message): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'SMS-Provider nicht konfiguriert (SMS_API_KEY)'];
        }

        $to = $this->normalizePhoneNumber($to);
        if (!$to) {
            return ['success' => false, 'error' => 'Ungueltige Telefonnummer'];
        }

        $result = match ($this->provider) {
            'twilio' => $this->sendViaTwilio($to, $message, false),
            'messagebird' => $this->sendViaMessageBird($to, $message),
            'vonage' => $this->sendViaVonage($to, $message),
            default => ['success' => false, 'error' => 'Unbekannter Provider: ' . $this->provider],
        };

        $this->logMessage($to, $message, 'sms', $result['success'], $result['error'] ?? null);
        return $result;
    }

    /**
     * WhatsApp-Nachricht senden
     */
    public function sendWhatsApp(string $to, string $message): array
    {
        if (!$this->whatsappEnabled) {
            return ['success' => false, 'error' => 'WhatsApp nicht aktiviert (WHATSAPP_ENABLED)'];
        }
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'SMS-Provider nicht konfiguriert'];
        }

        $to = $this->normalizePhoneNumber($to);
        if (!$to) {
            return ['success' => false, 'error' => 'Ungueltige Telefonnummer'];
        }

        // Twilio unterstuetzt WhatsApp nativ
        if ($this->provider === 'twilio') {
            $result = $this->sendViaTwilio($to, $message, true);
        } else {
            $result = ['success' => false, 'error' => 'WhatsApp nur mit Twilio-Provider unterstuetzt'];
        }

        $this->logMessage($to, $message, 'whatsapp', $result['success'], $result['error'] ?? null);
        return $result;
    }

    /**
     * Projekt-Erinnerung per SMS/WhatsApp
     */
    public function sendProjectReminder(int $projectId, string $channel = 'sms'): array
    {
        $sql = "SELECT p.projects_name, p.projects_dates_use_start,
                       c.clients_name, c.clients_phone
                FROM projects p
                LEFT JOIN clients c ON p.clients_id = c.clients_id
                WHERE p.projects_id = ? AND p.projects_deleted = 0";
        $project = $this->db->rawQuery($sql, [$projectId]);

        if (!$project) return ['success' => false, 'error' => 'Projekt nicht gefunden'];
        $project = $project[0];

        if (empty($project['clients_phone'])) {
            return ['success' => false, 'error' => 'Keine Telefonnummer hinterlegt'];
        }

        $startDate = date('d.m.Y', strtotime($project['projects_dates_use_start']));
        $message = "Erinnerung: Ihr Projekt \"{$project['projects_name']}\" startet am {$startDate}. Bei Fragen kontaktieren Sie uns gerne.";

        return $channel === 'whatsapp'
            ? $this->sendWhatsApp($project['clients_phone'], $message)
            : $this->sendSms($project['clients_phone'], $message);
    }

    /**
     * Rueckgabe-Erinnerung per SMS
     */
    public function sendReturnReminder(int $projectId): array
    {
        $sql = "SELECT p.projects_name, p.projects_dates_use_end,
                       c.clients_name, c.clients_phone
                FROM projects p
                LEFT JOIN clients c ON p.clients_id = c.clients_id
                WHERE p.projects_id = ? AND p.projects_deleted = 0";
        $project = $this->db->rawQuery($sql, [$projectId]);

        if (!$project || empty($project[0]['clients_phone'])) {
            return ['success' => false, 'error' => 'Kein Kontakt oder Telefonnummer'];
        }

        $endDate = date('d.m.Y', strtotime($project[0]['projects_dates_use_end']));
        $message = "Bitte denken Sie an die Equipment-Rueckgabe fuer \"{$project[0]['projects_name']}\" am {$endDate}.";

        return $this->sendSms($project[0]['clients_phone'], $message);
    }

    private function sendViaTwilio(string $to, string $message, bool $whatsapp): array
    {
        $fromNumber = $whatsapp ? "whatsapp:{$this->fromNumber}" : $this->fromNumber;
        $toNumber = $whatsapp ? "whatsapp:{$to}" : $to;

        $accountSid = $this->apiKey;
        $authToken = $this->apiSecret;

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'From' => $fromNumber,
                'To' => $toNumber,
                'Body' => $message,
            ]),
            CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201) {
            $data = json_decode($response, true);
            return ['success' => true, 'message_sid' => $data['sid'] ?? ''];
        }

        $data = json_decode($response, true);
        return ['success' => false, 'error' => $data['message'] ?? "HTTP {$httpCode}"];
    }

    private function sendViaMessageBird(string $to, string $message): array
    {
        $ch = curl_init('https://rest.messagebird.com/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'originator' => $this->fromNumber,
                'recipients' => [$to],
                'body' => $message,
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: AccessKey ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 300)
            ? ['success' => true]
            : ['success' => false, 'error' => "HTTP {$httpCode}"];
    }

    private function sendViaVonage(string $to, string $message): array
    {
        $ch = curl_init('https://rest.nexmo.com/sms/json');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
                'from' => $this->fromNumber,
                'to' => $to,
                'text' => $message,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        $status = $data['messages'][0]['status'] ?? '1';
        return $status === '0'
            ? ['success' => true]
            : ['success' => false, 'error' => $data['messages'][0]['error-text'] ?? "Fehler"];
    }

    private function normalizePhoneNumber(string $phone): ?string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (empty($phone)) return null;

        // Deutsche Nummer ohne Landesvorwahl
        if (str_starts_with($phone, '0') && !str_starts_with($phone, '00')) {
            $phone = '+49' . substr($phone, 1);
        }
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }
        if (!str_starts_with($phone, '+')) {
            $phone = '+49' . $phone;
        }

        return strlen($phone) >= 10 ? $phone : null;
    }

    private function logMessage(string $to, string $message, string $channel, bool $success, ?string $error = null): void
    {
        $this->db->insert('sms_log', [
            'phone_number' => $to,
            'message' => mb_substr($message, 0, 500),
            'channel' => $channel,
            'success' => $success ? 1 : 0,
            'error' => $error,
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
