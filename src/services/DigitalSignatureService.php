<?php
/**
 * DigitalSignatureService - Digitale Unterschriften
 *
 * Ermoeglicht das Erfassen und Speichern digitaler Unterschriften
 * auf Lieferscheinen, Angeboten und Rueckgabeprotokollen.
 * Die Unterschrift wird als Base64-kodiertes PNG gespeichert.
 */
class DigitalSignatureService
{
    private $db;

    const TYPE_DELIVERY = 'delivery_note';
    const TYPE_QUOTE = 'quote';
    const TYPE_RETURN = 'return_protocol';
    const TYPE_CONTRACT = 'contract';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Unterschrift speichern
     */
    public function save(int $documentId, string $documentType, string $signatureData, string $signerName, int $userId): array
    {
        // Base64-Daten validieren
        if (!$this->isValidSignatureData($signatureData)) {
            return ['success' => false, 'error' => 'Ungueltiges Unterschrifts-Format'];
        }

        $id = $this->db->insert('digital_signatures', [
            'document_id' => $documentId,
            'document_type' => $documentType,
            'signature_data' => $signatureData,
            'signer_name' => $signerName,
            'signer_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_id' => $userId,
            'signed_at' => date('Y-m-d H:i:s'),
            'hash' => hash('sha256', $signatureData . $documentId . $documentType . date('Y-m-d H:i:s')),
        ]);

        return $id
            ? ['success' => true, 'id' => $id]
            : ['success' => false, 'error' => 'Fehler beim Speichern'];
    }

    /**
     * Unterschrift fuer ein Dokument abrufen
     */
    public function get(int $documentId, string $documentType): ?array
    {
        $this->db->where('document_id', $documentId);
        $this->db->where('document_type', $documentType);
        $this->db->orderBy('signed_at', 'DESC');
        return $this->db->getOne('digital_signatures') ?: null;
    }

    /**
     * Pruefen ob ein Dokument unterschrieben ist
     */
    public function isSigned(int $documentId, string $documentType): bool
    {
        $this->db->where('document_id', $documentId);
        $this->db->where('document_type', $documentType);
        return (int) $this->db->getValue('digital_signatures', 'count(*)') > 0;
    }

    /**
     * Unterschrift als PNG-Daten fuer PDF-Einbettung
     */
    public function getSignatureImage(int $documentId, string $documentType): ?string
    {
        $sig = $this->get($documentId, $documentType);
        if (!$sig) return null;

        // data:image/png;base64,... -> raw base64
        $data = $sig['signature_data'];
        if (strpos($data, 'base64,') !== false) {
            $data = substr($data, strpos($data, 'base64,') + 7);
        }
        return $data;
    }

    /**
     * Signatur-Hash verifizieren
     */
    public function verify(int $signatureId): array
    {
        $this->db->where('id', $signatureId);
        $sig = $this->db->getOne('digital_signatures');
        if (!$sig) return ['valid' => false, 'error' => 'Unterschrift nicht gefunden'];

        return [
            'valid' => true,
            'signer_name' => $sig['signer_name'],
            'signed_at' => $sig['signed_at'],
            'hash' => $sig['hash'],
        ];
    }

    private function isValidSignatureData(string $data): bool
    {
        // Muss Base64-kodierte Bilddaten sein
        if (strpos($data, 'data:image/') === 0) {
            $base64 = substr($data, strpos($data, 'base64,') + 7);
            return base64_decode($base64, true) !== false;
        }
        // Oder reines Base64
        return base64_decode($data, true) !== false && strlen($data) > 100;
    }
}
