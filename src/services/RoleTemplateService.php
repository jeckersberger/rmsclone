<?php
/**
 * RoleTemplateService - Vordefinierte Rollenprofile fuer Firmen
 *
 * Stellt Standard-Rollenprofile bereit, die beim Einrichten einer Firma
 * automatisch oder manuell angelegt werden koennen.
 * Jede Firma kann die Rollen danach individuell anpassen.
 */
class RoleTemplateService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Alle verfuegbaren Rollenvorlagen
     */
    public static function getTemplates(): array
    {
        return [
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Vollzugriff auf alle Funktionen inkl. Einstellungen und Benutzerverwaltung',
                'rank' => 1,
                'permissions' => self::getAdminPermissions(),
            ],
            'buchhalter' => [
                'name' => 'Buchhaltung',
                'description' => 'Rechnungen, Mahnungen, DATEV, EUeR, Zahlungen, E-Mail',
                'rank' => 2,
                'permissions' => self::getBuchhalterPermissions(),
            ],
            'projektleiter' => [
                'name' => 'Projektleiter',
                'description' => 'Projekte verwalten, Angebote/Rechnungen erstellen, Kunden, E-Mail',
                'rank' => 3,
                'permissions' => self::getProjektleiterPermissions(),
            ],
            'lager_technik' => [
                'name' => 'Lager / Technik',
                'description' => 'Assets, Inventur, Standorte, Barcodes, Wartung, Schadensmeldungen',
                'rank' => 4,
                'permissions' => self::getLagerTechnikPermissions(),
            ],
            'mitarbeiter' => [
                'name' => 'Mitarbeiter',
                'description' => 'Grundlegender Zugriff: eigene Projekte sehen, Crew-Rollen, Training',
                'rank' => 5,
                'permissions' => self::getMitarbeiterPermissions(),
            ],
            'extern' => [
                'name' => 'Externer Zugang',
                'description' => 'Minimaler Lesezugriff fuer externe Partner oder Praktikanten',
                'rank' => 6,
                'permissions' => self::getExternPermissions(),
            ],
        ];
    }

    /**
     * Alle Standard-Rollen fuer eine Instance anlegen
     *
     * @return array ['created' => int, 'skipped' => int, 'roles' => array]
     */
    public function createDefaultRoles(int $instanceId): array
    {
        $templates = self::getTemplates();
        $result = ['created' => 0, 'skipped' => 0, 'roles' => []];

        foreach ($templates as $key => $template) {
            // Pruefen ob Rolle mit gleichem Namen schon existiert
            $this->db->where('instances_id', $instanceId);
            $this->db->where('instancePositions_displayName', $template['name']);
            $this->db->where('instancePositions_deleted', 0);
            $existing = $this->db->getOne('instancePositions', null, ['instancePositions_id']);

            if ($existing) {
                $result['skipped']++;
                $result['roles'][] = [
                    'key' => $key,
                    'name' => $template['name'],
                    'status' => 'skipped',
                    'id' => $existing['instancePositions_id'],
                ];
                continue;
            }

            $positionId = $this->db->insert('instancePositions', [
                'instances_id' => $instanceId,
                'instancePositions_displayName' => $template['name'],
                'instancePositions_rank' => $template['rank'],
                'instancePositions_actions' => implode(',', $template['permissions']),
                'instancePositions_deleted' => 0,
            ]);

            if ($positionId) {
                $result['created']++;
                $result['roles'][] = [
                    'key' => $key,
                    'name' => $template['name'],
                    'status' => 'created',
                    'id' => $positionId,
                ];
            }
        }

        return $result;
    }

    /**
     * Einzelne Rollenvorlage anlegen
     */
    public function createRole(int $instanceId, string $templateKey): ?int
    {
        $templates = self::getTemplates();
        if (!isset($templates[$templateKey])) return null;

        $template = $templates[$templateKey];
        return $this->db->insert('instancePositions', [
            'instances_id' => $instanceId,
            'instancePositions_displayName' => $template['name'],
            'instancePositions_rank' => $template['rank'],
            'instancePositions_actions' => implode(',', $template['permissions']),
            'instancePositions_deleted' => 0,
        ]) ?: null;
    }

    // ===== Permission-Sets fuer jede Rolle =====

    private static function getAdminPermissions(): array
    {
        // Admin bekommt alle existierenden Permissions
        require_once __DIR__ . '/../common/libs/Auth/instanceActions.php';
        return array_keys($GLOBALS['instanceActions'] ?? $instanceActions);
    }

    private static function getBuchhalterPermissions(): array
    {
        return [
            // Dokumente & Rechnungen
            'DOCUMENTS:VIEW',
            'DOCUMENTS:CREATE',
            'DOCUMENTS:EDIT',
            'DOCUMENTS:DELETE',
            'DOCUMENTS:TEMPLATES:EDIT',
            // Finanzen
            'FINANCE:PAYMENTS_LEDGER:VIEW',
            'PROJECTS:PROJECT_PAYMENTS:VIEW',
            'PROJECTS:PROJECT_PAYMENTS:VIEW:FILE_ATTACHMENTS',
            'PROJECTS:PROJECT_PAYMENTS:CREATE',
            'PROJECTS:PROJECT_PAYMENTS:CREATE:FILE_ATTACHMENTS',
            'PROJECTS:PROJECT_PAYMENTS:DELETE',
            // Mahnwesen
            'DUNNING:VIEW',
            'DUNNING:CREATE',
            'DUNNING:EDIT',
            // DATEV
            'DATEV:EXPORT',
            // EUeR
            'EUER:VIEW',
            // Berichte
            'REPORTS:VIEW',
            'BUSINESS:BUSINESS_STATS:VIEW',
            // E-Mail
            'EMAIL_INBOX:VIEW',
            'EMAIL_INBOX:VIEW:ATTACHMENTS',
            'EMAIL_INBOX:EDIT',
            'EMAIL_OUTBOX:VIEW',
            'EMAIL_OUTBOX:SEND',
            // Projekte (Lesezugriff)
            'PROJECTS:VIEW',
            'PROJECTS:EDIT:INVOICE_NOTES',
            // Kunden
            'CLIENTS:VIEW',
            'CLIENTS:EDIT',
            // Wiederkehrende
            'RECURRING:VIEW',
            'RECURRING:EDIT',
            // DSGVO
            'DSGVO:VIEW',
            // Partner
            'PARTNERS:VIEW',
            // KI
            'AI:VIEW',
        ];
    }

    private static function getProjektleiterPermissions(): array
    {
        return [
            // Projekte (Vollzugriff)
            'PROJECTS:VIEW',
            'PROJECTS:CREATE',
            'PROJECTS:EDIT:CLIENT',
            'PROJECTS:EDIT:LEAD',
            'PROJECTS:EDIT:DESCRIPTION_AND_SUB_PROJECTS',
            'PROJECTS:EDIT:DATES',
            'PROJECTS:EDIT:NAME',
            'PROJECTS:EDIT:STATUS',
            'PROJECTS:EDIT:ADDRESS',
            'PROJECTS:EDIT:INVOICE_NOTES',
            'PROJECTS:EDIT:DELIVERY_NOTES',
            'PROJECTS:EDIT:PROJECT_TYPE',
            'PROJECTS:ARCHIVE',
            'PROJECTS:PROJECT_NOTES:CREATE:NOTES',
            'PROJECTS:PROJECT_NOTES:EDIT:NOTES',
            'PROJECTS:PROJECT_FLIE_ATTACHMENTS:CREATE',
            // Projekt-Assets
            'PROJECTS:PROJECT_ASSETS:CREATE:ASSIGN_AND_UNASSIGN',
            'PROJECTS:PROJECT_ASSETS:CREATE:ASSIGN_ALL_BUSINESS_ASSETS',
            'PROJECTS:PROJECT_ASSETS:EDIT:ASSIGNMNET_COMMENT',
            'PROJECTS:PROJECT_ASSETS:EDIT:CUSTOM_PRICE',
            'PROJECTS:PROJECT_ASSETS:EDIT:DISCOUNT',
            'PROJECTS:PROJECT_ASSETS:EDIT:ASSIGNMENT_STATUS',
            // Crew
            'PROJECTS:PROJECT_CREW:VIEW:VIEW_AND_APPLY_FOR_CREW_ROLES',
            'PROJECTS:PROJECT_CREW:VIEW',
            'PROJECTS:PROJECT_CREW:VIEW:EMAIL_CREW',
            'PROJECTS:PROJECT_CREW:CREATE',
            'PROJECTS:PROJECT_CREW:EDIT',
            'PROJECTS:PROJECT_CREW:EDIT:CREW_RANKS',
            'PROJECTS:PROJECT_CREW:EDIT:CREW_RECRUITMENT',
            // Zahlungen
            'PROJECTS:PROJECT_PAYMENTS:VIEW',
            'PROJECTS:PROJECT_PAYMENTS:VIEW:FILE_ATTACHMENTS',
            'PROJECTS:PROJECT_PAYMENTS:CREATE',
            'PROJECTS:PROJECT_PAYMENTS:CREATE:FILE_ATTACHMENTS',
            // Dokumente
            'DOCUMENTS:VIEW',
            'DOCUMENTS:CREATE',
            'DOCUMENTS:EDIT',
            // E-Mail
            'EMAIL_INBOX:VIEW',
            'EMAIL_INBOX:VIEW:ATTACHMENTS',
            'EMAIL_INBOX:EDIT',
            'EMAIL_OUTBOX:SEND',
            'EMAIL_OUTBOX:VIEW',
            // Kunden
            'CLIENTS:VIEW',
            'CLIENTS:CREATE',
            'CLIENTS:EDIT',
            // Assets (Lesezugriff)
            'ASSETS:VIEW',
            'ASSETS:ASSET_BARCODES:VIEW',
            'ASSETS:ASSET_CATEGORIES:VIEW',
            // Standorte
            'LOCATIONS:VIEW',
            // Partner
            'PARTNERS:VIEW',
            'PARTNERS:CREATE',
            'PARTNERS:EDIT',
            // Wiederkehrende
            'RECURRING:VIEW',
            // Schaeden
            'DAMAGE:VIEW',
            'DAMAGE:CREATE',
            // KI
            'AI:VIEW',
            // Berichte
            'REPORTS:VIEW',
            'BUSINESS:BUSINESS_STATS:VIEW',
        ];
    }

    private static function getLagerTechnikPermissions(): array
    {
        return [
            // Assets (Vollzugriff)
            'ASSETS:VIEW',
            'ASSETS:CREATE',
            'ASSETS:EDIT',
            'ASSETS:EDIT:OVVERRIDES',
            'ASSETS:ARCHIVE',
            'ASSETS:TRANSFER',
            'ASSETS:ASSET_BARCODES:VIEW',
            'ASSETS:ASSET_BARCODES:VIEW:SCAN_IN_APP',
            'ASSETS:ASSET_BARCODES:EDIT:ASSOCIATE_UNNASOCIATED_BARCODES_WITH_ASSETS',
            'ASSETS:ASSET_BARCODES:DELETE',
            'ASSETS:ASSET_CATEGORIES:VIEW',
            'ASSETS:ASSET_CATEGORIES:CREATE',
            'ASSETS:ASSET_CATEGORIES:EDIT',
            'ASSETS:ASSET_GROUPS:CREATE',
            'ASSETS:ASSET_GROUPS:EDIT',
            'ASSETS:ASSET_GROUPS:EDIT:ASSETS_WITHIN_GROUP',
            'ASSETS:ASSET_TYPES:CREATE',
            'ASSETS:ASSET_TYPES:EDIT',
            'ASSETS:ASSET_FILE_ATTACHMENTS:CREATE',
            'ASSETS:ASSET_FILE_ATTACHMENTS:VIEW',
            'ASSETS:ASSET_TYPE_FILE_ATTACHMENTS:CREATE',
            'ASSETS:ASSET_TYPE_FILE_ATTACHMENTS:VIEW',
            'ASSETS:FILE_ATTACHMENTS:EDIT',
            'ASSETS:MANUFACTURERS:CREATE',
            // Standorte
            'LOCATIONS:VIEW',
            'LOCATIONS:CREATE',
            'LOCATIONS:EDIT',
            'LOCATIONS:LOCATION_BARCODES:VIEW',
            'LOCATIONS:LOCATION_FILE_ATTACHMENTS:CREATE',
            'LOCATIONS:LOCATION_FILE_ATTACHMENTS:VIEW',
            // Wartung
            'MAINTENANCE_JOBS:VIEW',
            'MAINTENANCE_JOBS:EDIT',
            'MAINTENANCE_JOBS:EDIT:NAME',
            'MAINTENANCE_JOBS:EDIT:STATUS',
            'MAINTENANCE_JOBS:EDIT:JOB_DUE_DATE',
            'MAINTENANCE_JOBS:EDIT:JOB_PRIORITY',
            'MAINTENANCE_JOBS:EDIT:USER_ASSIGNED_TO_JOB',
            'MAINTENANCE_JOBS:EDIT:USERS_TAGGED_IN_JOB',
            'MAINTENANCE_JOBS:EDIT:ADD_MESSAGE_TO_JOB',
            'MAINTENANCE_JOBS:EDIT:ADD_ASSETS',
            'MAINTENANCE_JOBS:EDIT:ASSET_FLAGS',
            'MAINTENANCE_JOBS:EDIT:ASSET_BLOCKS',
            'MAINTENANCE_JOBS:MAINTENANCE_JOBS_FILE_ATTACHMENTS:CREATE',
            // Inventur
            'INVENTORY:VIEW',
            'INVENTORY:CREATE',
            // Schaeden
            'DAMAGE:VIEW',
            'DAMAGE:CREATE',
            'DAMAGE:EDIT',
            // Projekte (Lesezugriff fuer Asset-Zuordnung)
            'PROJECTS:VIEW',
            'PROJECTS:PROJECT_ASSETS:CREATE:ASSIGN_AND_UNASSIGN',
            'PROJECTS:PROJECT_ASSETS:EDIT:ASSIGNMENT_STATUS',
        ];
    }

    private static function getMitarbeiterPermissions(): array
    {
        return [
            // Projekte (Grundzugriff)
            'PROJECTS:VIEW',
            'PROJECTS:PROJECT_NOTES:CREATE:NOTES',
            'PROJECTS:PROJECT_CREW:VIEW:VIEW_AND_APPLY_FOR_CREW_ROLES',
            'PROJECTS:PROJECT_CREW:VIEW',
            'PROJECTS:PROJECT_FLIE_ATTACHMENTS:CREATE',
            // Assets (Nur lesen)
            'ASSETS:VIEW',
            'ASSETS:ASSET_BARCODES:VIEW',
            'ASSETS:ASSET_BARCODES:VIEW:SCAN_IN_APP',
            'ASSETS:ASSET_CATEGORIES:VIEW',
            'ASSETS:ASSET_FILE_ATTACHMENTS:VIEW',
            // Standorte (Nur lesen)
            'LOCATIONS:VIEW',
            // Training
            'TRAINING:VIEW',
            // Schaeden (Melden)
            'DAMAGE:VIEW',
            'DAMAGE:CREATE',
            // Wartung (Nur lesen + Nachrichten)
            'MAINTENANCE_JOBS:VIEW',
            'MAINTENANCE_JOBS:EDIT:ADD_MESSAGE_TO_JOB',
        ];
    }

    private static function getExternPermissions(): array
    {
        return [
            // Minimal: Projekte und Assets nur lesen
            'PROJECTS:VIEW',
            'PROJECTS:PROJECT_CREW:VIEW:VIEW_AND_APPLY_FOR_CREW_ROLES',
            'ASSETS:VIEW',
            'ASSETS:ASSET_BARCODES:VIEW',
            'LOCATIONS:VIEW',
            'TRAINING:VIEW',
        ];
    }
}
