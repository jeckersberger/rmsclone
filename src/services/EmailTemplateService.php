<?php
/**
 * EmailTemplateService - Konfigurierbare E-Mail-Vorlagen
 *
 * Twig-basierte E-Mail-Templates mit Platzhaltern fuer:
 * - Projektbestaetigung
 * - Termin-Erinnerung
 * - Feedback-Anfrage
 * - Rechnungsversand
 * - Mahnungen
 */
class EmailTemplateService
{
    private $db;

    /** Standard-Template-Typen */
    const TYPE_PROJECT_CONFIRMATION = 'project_confirmation';
    const TYPE_PROJECT_REMINDER = 'project_reminder';
    const TYPE_FEEDBACK_REQUEST = 'feedback_request';
    const TYPE_INVOICE = 'invoice';
    const TYPE_DUNNING = 'dunning';
    const TYPE_RETURN_REMINDER = 'return_reminder';
    const TYPE_CUSTOM = 'custom';

    const VALID_TYPES = [
        self::TYPE_PROJECT_CONFIRMATION,
        self::TYPE_PROJECT_REMINDER,
        self::TYPE_FEEDBACK_REQUEST,
        self::TYPE_INVOICE,
        self::TYPE_DUNNING,
        self::TYPE_RETURN_REMINDER,
        self::TYPE_CUSTOM,
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Template erstellen oder aktualisieren
     */
    public function save(int $instanceId, string $type, string $subject, string $body, string $name = ''): array
    {
        if (!in_array($type, self::VALID_TYPES)) {
            return ['success' => false, 'error' => 'Ungueltiger Template-Typ'];
        }

        // Pruefen ob Template existiert
        $this->db->where('instances_id', $instanceId);
        $this->db->where('type', $type);
        $this->db->where('deleted', 0);
        $existing = $this->db->getOne('email_templates', ['id']);

        $data = [
            'instances_id' => $instanceId,
            'type' => $type,
            'name' => $name ?: $this->getDefaultName($type),
            'subject' => $subject,
            'body' => $body,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db->where('id', $existing['id']);
            $this->db->update('email_templates', $data);
            return ['success' => true, 'id' => $existing['id']];
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['deleted'] = 0;
        $id = $this->db->insert('email_templates', $data);
        return $id ? ['success' => true, 'id' => $id] : ['success' => false, 'error' => 'Fehler beim Speichern'];
    }

    /**
     * Template laden und rendern
     */
    public function render(int $instanceId, string $type, array $variables = []): ?array
    {
        $template = $this->getTemplate($instanceId, $type);
        if (!$template) {
            $template = $this->getDefaultTemplate($type);
        }
        if (!$template) return null;

        $subject = $this->replaceVariables($template['subject'], $variables);
        $body = $this->replaceVariables($template['body'], $variables);

        return [
            'subject' => $subject,
            'body' => $body,
            'type' => $type,
        ];
    }

    /**
     * Template abrufen
     */
    public function getTemplate(int $instanceId, string $type): ?array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('type', $type);
        $this->db->where('deleted', 0);
        return $this->db->getOne('email_templates') ?: null;
    }

    /**
     * Alle Templates einer Instanz auflisten
     */
    public function list(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->orderBy('type', 'ASC');
        $templates = $this->db->get('email_templates') ?: [];

        // Standard-Templates fuer fehlende Typen ergaenzen
        $existingTypes = array_column($templates, 'type');
        foreach (self::VALID_TYPES as $type) {
            if ($type === self::TYPE_CUSTOM) continue;
            if (!in_array($type, $existingTypes)) {
                $templates[] = $this->getDefaultTemplate($type) + ['is_default' => true];
            }
        }

        return $templates;
    }

    /**
     * Template loeschen (zurueck zum Standard)
     */
    public function delete(int $id, int $instanceId): bool
    {
        $this->db->where('id', $id);
        $this->db->where('instances_id', $instanceId);
        return (bool) $this->db->update('email_templates', ['deleted' => 1]);
    }

    /**
     * Verfuegbare Platzhalter fuer einen Template-Typ
     */
    public function getAvailableVariables(string $type): array
    {
        $common = [
            '{{firmenname}}' => 'Name des Unternehmens',
            '{{datum}}' => 'Aktuelles Datum',
        ];

        $typeVars = [
            self::TYPE_PROJECT_CONFIRMATION => [
                '{{kunde}}' => 'Kundenname',
                '{{projekt}}' => 'Projektname',
                '{{startdatum}}' => 'Projekt-Startdatum',
                '{{enddatum}}' => 'Projekt-Enddatum',
                '{{gesamtpreis}}' => 'Gesamtpreis',
                '{{ansprechpartner}}' => 'Ansprechpartner',
            ],
            self::TYPE_PROJECT_REMINDER => [
                '{{kunde}}' => 'Kundenname',
                '{{projekt}}' => 'Projektname',
                '{{startdatum}}' => 'Projekt-Startdatum',
                '{{tage_bis_start}}' => 'Tage bis zum Start',
            ],
            self::TYPE_FEEDBACK_REQUEST => [
                '{{kunde}}' => 'Kundenname',
                '{{projekt}}' => 'Projektname',
                '{{enddatum}}' => 'Projekt-Enddatum',
            ],
            self::TYPE_INVOICE => [
                '{{kunde}}' => 'Kundenname',
                '{{rechnungsnummer}}' => 'Rechnungsnummer',
                '{{betrag}}' => 'Rechnungsbetrag',
                '{{faelligkeitsdatum}}' => 'Faelligkeitsdatum',
            ],
            self::TYPE_DUNNING => [
                '{{kunde}}' => 'Kundenname',
                '{{rechnungsnummer}}' => 'Rechnungsnummer',
                '{{betrag}}' => 'Offener Betrag',
                '{{mahnstufe}}' => 'Mahnstufe',
            ],
            self::TYPE_RETURN_REMINDER => [
                '{{kunde}}' => 'Kundenname',
                '{{projekt}}' => 'Projektname',
                '{{rueckgabedatum}}' => 'Rueckgabedatum',
                '{{equipment_liste}}' => 'Equipment-Liste',
            ],
        ];

        return array_merge($common, $typeVars[$type] ?? []);
    }

    private function replaceVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, (string) $value, $template);
        }
        return $template;
    }

    private function getDefaultName(string $type): string
    {
        $names = [
            self::TYPE_PROJECT_CONFIRMATION => 'Projektbestaetigung',
            self::TYPE_PROJECT_REMINDER => 'Termin-Erinnerung',
            self::TYPE_FEEDBACK_REQUEST => 'Feedback-Anfrage',
            self::TYPE_INVOICE => 'Rechnungsversand',
            self::TYPE_DUNNING => 'Mahnung',
            self::TYPE_RETURN_REMINDER => 'Rueckgabe-Erinnerung',
            self::TYPE_CUSTOM => 'Benutzerdefiniert',
        ];
        return $names[$type] ?? $type;
    }

    private function getDefaultTemplate(string $type): array
    {
        $templates = [
            self::TYPE_PROJECT_CONFIRMATION => [
                'type' => $type,
                'name' => 'Projektbestaetigung',
                'subject' => 'Bestaetigung: {{projekt}} am {{startdatum}}',
                'body' => "Sehr geehrte/r {{kunde}},\n\nhiermit bestaetigen wir Ihnen das Projekt \"{{projekt}}\".\n\nZeitraum: {{startdatum}} bis {{enddatum}}\nGesamtpreis: {{gesamtpreis}} EUR\n\nBei Fragen stehen wir Ihnen gerne zur Verfuegung.\n\nMit freundlichen Gruessen\n{{firmenname}}",
            ],
            self::TYPE_PROJECT_REMINDER => [
                'type' => $type,
                'name' => 'Termin-Erinnerung',
                'subject' => 'Erinnerung: {{projekt}} in {{tage_bis_start}} Tagen',
                'body' => "Sehr geehrte/r {{kunde}},\n\nwir moechten Sie daran erinnern, dass das Projekt \"{{projekt}}\" am {{startdatum}} beginnt.\n\nBitte stellen Sie sicher, dass alle Vorbereitungen getroffen sind.\n\nMit freundlichen Gruessen\n{{firmenname}}",
            ],
            self::TYPE_FEEDBACK_REQUEST => [
                'type' => $type,
                'name' => 'Feedback-Anfrage',
                'subject' => 'Wie war Ihr Erlebnis? Feedback zu {{projekt}}',
                'body' => "Sehr geehrte/r {{kunde}},\n\nIhr Projekt \"{{projekt}}\" wurde am {{enddatum}} abgeschlossen.\n\nWir wuerden uns freuen, wenn Sie uns Ihr Feedback mitteilen koennten. Ihre Meinung hilft uns, unseren Service stetig zu verbessern.\n\nVielen Dank!\n\nMit freundlichen Gruessen\n{{firmenname}}",
            ],
            self::TYPE_INVOICE => [
                'type' => $type,
                'name' => 'Rechnungsversand',
                'subject' => 'Rechnung {{rechnungsnummer}}',
                'body' => "Sehr geehrte/r {{kunde}},\n\nim Anhang finden Sie unsere Rechnung {{rechnungsnummer}} ueber {{betrag}} EUR.\n\nBitte ueberweisen Sie den Betrag bis zum {{faelligkeitsdatum}}.\n\nMit freundlichen Gruessen\n{{firmenname}}",
            ],
            self::TYPE_DUNNING => [
                'type' => $type,
                'name' => 'Mahnung',
                'subject' => '{{mahnstufe}}. Mahnung - Rechnung {{rechnungsnummer}}',
                'body' => "Sehr geehrte/r {{kunde}},\n\ntrotz unserer bisherigen Zahlungserinnerungen konnten wir fuer die Rechnung {{rechnungsnummer}} noch keinen Zahlungseingang feststellen.\n\nOffener Betrag: {{betrag}} EUR\n\nBitte ueberweisen Sie den Betrag umgehend.\n\nMit freundlichen Gruessen\n{{firmenname}}",
            ],
            self::TYPE_RETURN_REMINDER => [
                'type' => $type,
                'name' => 'Rueckgabe-Erinnerung',
                'subject' => 'Erinnerung: Equipment-Rueckgabe am {{rueckgabedatum}}',
                'body' => "Sehr geehrte/r {{kunde}},\n\nbitte denken Sie an die Rueckgabe des Equipments fuer das Projekt \"{{projekt}}\" am {{rueckgabedatum}}.\n\n{{equipment_liste}}\n\nMit freundlichen Gruessen\n{{firmenname}}",
            ],
        ];

        return $templates[$type] ?? ['type' => $type, 'name' => $type, 'subject' => '', 'body' => ''];
    }
}
