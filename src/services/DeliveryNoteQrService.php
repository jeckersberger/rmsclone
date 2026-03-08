<?php
/**
 * DeliveryNoteQrService - QR-Code auf Lieferscheinen
 *
 * Generiert QR-Codes fuer Lieferscheine, die per Smartphone
 * gescannt werden koennen und den zugehoerigen Packauftrag
 * in einer mobilen Ansicht oeffnen.
 */
class DeliveryNoteQrService
{
    private $db;
    private $baseUrl;

    public function __construct($db)
    {
        $this->db = $db;
        $this->baseUrl = rtrim(getenv('ROOT_URL') ?: '', '/');
    }

    /**
     * QR-Code URL fuer einen Lieferschein generieren
     */
    public function generatePackingListUrl(int $deliveryNoteId): string
    {
        // Token generieren fuer sicheren Zugriff ohne Login
        $token = $this->getOrCreateToken($deliveryNoteId);
        return $this->baseUrl . '/mobile/packing.php?token=' . $token;
    }

    /**
     * QR-Code als Data-URI fuer PDF-Einbettung
     */
    public function generateQrDataUri(int $deliveryNoteId, int $size = 150): string
    {
        $url = $this->generatePackingListUrl($deliveryNoteId);

        // Versuche lokale QR-Library
        if (class_exists('QRcode')) {
            ob_start();
            \QRcode::png($url, null, QR_ECLEVEL_M, max(1, intval($size / 50)), 1);
            $imageData = ob_get_clean();
            return 'data:image/png;base64,' . base64_encode($imageData);
        }

        // Fallback: SVG-basierter QR-Code Placeholder
        return $this->generateSimpleQrSvg($url, $size);
    }

    /**
     * Token fuer sicheren Lieferschein-Zugriff
     */
    public function getOrCreateToken(int $deliveryNoteId): string
    {
        $this->db->where('delivery_note_id', $deliveryNoteId);
        $this->db->where('active', 1);
        $existing = $this->db->getOne('delivery_note_tokens', ['token']);
        if ($existing) return $existing['token'];

        $token = bin2hex(random_bytes(16));
        $this->db->insert('delivery_note_tokens', [
            'delivery_note_id' => $deliveryNoteId,
            'token' => $token,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        return $token;
    }

    /**
     * Token validieren und Lieferschein-ID zurueckgeben
     */
    public function validateToken(string $token): ?int
    {
        $this->db->where('token', $token);
        $this->db->where('active', 1);
        $this->db->where('expires_at', date('Y-m-d H:i:s'), '>=');
        $result = $this->db->getOne('delivery_note_tokens', ['delivery_note_id']);
        return $result ? (int) $result['delivery_note_id'] : null;
    }

    /**
     * Packauftrag-Daten fuer mobile Ansicht
     */
    public function getPackingListData(int $deliveryNoteId): ?array
    {
        $sql = "SELECT dn.*, p.projects_name, p.projects_dates_use_start,
                       c.clients_name, c.clients_address
                FROM delivery_notes dn
                LEFT JOIN projects p ON dn.projects_id = p.projects_id
                LEFT JOIN clients c ON p.clients_id = c.clients_id
                WHERE dn.id = ?";
        $note = $this->db->rawQuery($sql, [$deliveryNoteId]);
        if (!$note) return null;

        // Positionen laden
        $sql = "SELECT dni.*, at.assetTypes_name, a.assets_tag
                FROM delivery_note_items dni
                LEFT JOIN assets a ON dni.assets_id = a.assets_id
                LEFT JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE dni.delivery_note_id = ?
                ORDER BY dni.sort_order ASC";
        $items = $this->db->rawQuery($sql, [$deliveryNoteId]) ?: [];

        return [
            'delivery_note' => $note[0],
            'items' => $items,
        ];
    }

    private function generateSimpleQrSvg(string $data, int $size): string
    {
        $encoded = htmlspecialchars($data, ENT_QUOTES);
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$size}' height='{$size}' viewBox='0 0 {$size} {$size}'>";
        $svg .= "<rect width='100%' height='100%' fill='white' stroke='#333' stroke-width='2' rx='4'/>";
        $svg .= "<text x='50%' y='35%' text-anchor='middle' font-family='monospace' font-size='11' fill='#333'>QR-Code</text>";
        $svg .= "<text x='50%' y='50%' text-anchor='middle' font-family='monospace' font-size='9' fill='#666'>Packauftrag</text>";
        $svg .= "<text x='50%' y='70%' text-anchor='middle' font-family='monospace' font-size='7' fill='#999'>composer require</text>";
        $svg .= "<text x='50%' y='80%' text-anchor='middle' font-family='monospace' font-size='7' fill='#999'>endroid/qr-code</text>";
        $svg .= "</svg>";
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
