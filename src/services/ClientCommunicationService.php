<?php
/**
 * Kommunikationsprotokoll fuer Kunden
 *
 * Verwaltet die Kommunikationshistorie (E-Mail, Telefon, Meeting, Notiz, Brief)
 * zu jedem Kunden.
 */
class ClientCommunicationService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Neuen Kommunikationseintrag anlegen
     *
     * @param int    $clientId
     * @param string $type          email|phone|meeting|note|letter
     * @param string $subject       Betreff
     * @param string $content       Inhalt/Notizen
     * @param string $direction     inbound|outbound
     * @param string|null $contactPerson Ansprechpartner
     * @param int    $userId        Erstellt von
     * @param string|null $communicationDate Datum/Uhrzeit (falls null: jetzt)
     * @return int|false Insert-ID oder false
     */
    public function addEntry(
        int $clientId,
        string $type,
        string $subject,
        string $content,
        string $direction,
        ?string $contactPerson,
        int $userId,
        ?string $communicationDate = null
    ) {
        $validTypes = ['email', 'phone', 'meeting', 'note', 'letter'];
        if (!in_array($type, $validTypes)) {
            return false;
        }

        $validDirections = ['inbound', 'outbound'];
        if (!in_array($direction, $validDirections)) {
            return false;
        }

        $this->db->insert('client_communications', [
            'clients_id'         => $clientId,
            'type'               => $type,
            'subject'            => $subject,
            'content'            => $content,
            'contact_person'     => $contactPerson,
            'direction'          => $direction,
            'communication_date' => $communicationDate ?: date('Y-m-d H:i:s'),
            'created_by'         => $userId,
        ]);

        return $this->db->getInsertId();
    }

    /**
     * Kommunikationshistorie eines Kunden abrufen
     *
     * @param int $clientId
     * @param int $limit
     * @return array
     */
    public function getEntries(int $clientId, int $limit = 50): array
    {
        $this->db->where('cc.clients_id', $clientId);
        $this->db->join('users u', 'cc.created_by=u.users_userid', 'LEFT');
        $this->db->orderBy('cc.communication_date', 'DESC');
        return $this->db->get('client_communications cc', $limit, [
            'cc.*',
            'u.users_name1',
            'u.users_name2',
        ]) ?: [];
    }

    /**
     * Kommunikationseintrag loeschen
     *
     * @param int $entryId
     * @return bool
     */
    public function deleteEntry(int $entryId): bool
    {
        $this->db->where('id', $entryId);
        return $this->db->delete('client_communications');
    }

    /**
     * Einzelnen Eintrag holen (fuer Berechtigungspruefung)
     *
     * @param int $entryId
     * @return array|null
     */
    public function getEntry(int $entryId): ?array
    {
        $this->db->where('cc.id', $entryId);
        $this->db->join('clients c', 'cc.clients_id=c.clients_id', 'LEFT');
        $entry = $this->db->getOne('client_communications cc', [
            'cc.*',
            'c.instances_id',
        ]);
        return $entry ?: null;
    }
}
