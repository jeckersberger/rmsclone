<?php
/**
 * Ansprechpartner-Verwaltung (mehrere pro Kunde)
 *
 * CRUD fuer client_contacts sowie Hilfsmethoden fuer
 * Primaerkontakt und Kontaktliste.
 */
class ClientContactService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Alle Ansprechpartner eines Kunden laden
     */
    public function getContacts(int $clientId): array
    {
        $this->db->where('clients_id', $clientId);
        $this->db->orderBy('is_primary', 'DESC');
        $this->db->orderBy('name', 'ASC');
        return $this->db->get('client_contacts') ?: [];
    }

    /**
     * Primaeren Ansprechpartner ermitteln
     */
    public function getPrimaryContact(int $clientId): ?array
    {
        $this->db->where('clients_id', $clientId);
        $this->db->where('is_primary', 1);
        $contact = $this->db->getOne('client_contacts');
        return $contact ?: null;
    }

    /**
     * Einzelnen Kontakt laden
     */
    public function getContact(int $contactId): ?array
    {
        $this->db->where('id', $contactId);
        $contact = $this->db->getOne('client_contacts');
        return $contact ?: null;
    }

    /**
     * Neuen Ansprechpartner anlegen
     */
    public function createContact(int $clientId, array $data): ?int
    {
        // Falls als primaer markiert, alle anderen zuruecksetzen
        if (!empty($data['is_primary'])) {
            $this->clearPrimary($clientId);
        }

        $insert = [
            'clients_id' => $clientId,
            'name'       => $data['name'] ?? '',
            'position'   => $data['position'] ?? null,
            'email'      => $data['email'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'mobile'     => $data['mobile'] ?? null,
            'is_primary' => !empty($data['is_primary']) ? 1 : 0,
            'notes'      => $data['notes'] ?? null,
        ];

        $id = $this->db->insert('client_contacts', $insert);
        return $id ?: null;
    }

    /**
     * Ansprechpartner aktualisieren
     */
    public function updateContact(int $contactId, int $clientId, array $data): bool
    {
        if (!empty($data['is_primary'])) {
            $this->clearPrimary($clientId);
        }

        $update = [];
        $allowed = ['name', 'position', 'email', 'phone', 'mobile', 'is_primary', 'notes'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (isset($update['is_primary'])) {
            $update['is_primary'] = $update['is_primary'] ? 1 : 0;
        }

        if (empty($update)) return false;

        $this->db->where('id', $contactId);
        $this->db->where('clients_id', $clientId);
        return (bool)$this->db->update('client_contacts', $update);
    }

    /**
     * Ansprechpartner loeschen
     */
    public function deleteContact(int $contactId, int $clientId): bool
    {
        $this->db->where('id', $contactId);
        $this->db->where('clients_id', $clientId);
        return (bool)$this->db->delete('client_contacts');
    }

    /**
     * Primaer-Flag bei allen Kontakten eines Kunden zuruecksetzen
     */
    private function clearPrimary(int $clientId): void
    {
        $this->db->where('clients_id', $clientId);
        $this->db->update('client_contacts', ['is_primary' => 0]);
    }
}
