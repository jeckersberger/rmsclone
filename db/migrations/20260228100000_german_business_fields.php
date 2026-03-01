<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds German business fields to the instances table for
 * Kleinunternehmerregelung (KUR), GoBD-compliant invoicing,
 * and DSGVO basics. Also adds client tax ID field.
 */
final class GermanBusinessFields extends AbstractMigration
{
    public function change(): void
    {
        // --- instances: German business / tax fields ---
        $instances = $this->table('instances');

        $instanceCols = [
            'instances_taxNumber' => ['type' => 'string', 'opts' => [
                'limit' => 60, 'null' => true,
                'comment' => 'Steuernummer (z.B. 123/456/78901)'
            ]],
            'instances_vatId' => ['type' => 'string', 'opts' => [
                'limit' => 30, 'null' => true,
                'comment' => 'USt-IdNr. (z.B. DE123456789)'
            ]],
            'instances_kurEnabled' => ['type' => 'boolean', 'opts' => [
                'default' => 1,
                'comment' => 'Kleinunternehmerregelung aktiv (§19 UStG)'
            ]],
            'instances_vatRate' => ['type' => 'decimal', 'opts' => [
                'precision' => 5, 'scale' => 2, 'default' => 19.00,
                'comment' => 'Standard-MwSt-Satz in Prozent'
            ]],
            'instances_bankName' => ['type' => 'string', 'opts' => [
                'limit' => 120, 'null' => true,
                'comment' => 'Name der Bank'
            ]],
            'instances_bankIban' => ['type' => 'string', 'opts' => [
                'limit' => 34, 'null' => true,
                'comment' => 'IBAN'
            ]],
            'instances_bankBic' => ['type' => 'string', 'opts' => [
                'limit' => 11, 'null' => true,
                'comment' => 'BIC/SWIFT'
            ]],
            'instances_paymentTermDays' => ['type' => 'integer', 'opts' => [
                'default' => 14,
                'comment' => 'Standard-Zahlungsziel in Tagen'
            ]],
            'instances_courtOfJurisdiction' => ['type' => 'string', 'opts' => [
                'limit' => 120, 'null' => true,
                'comment' => 'Gerichtsstand'
            ]],
            'instances_ceoName' => ['type' => 'string', 'opts' => [
                'limit' => 200, 'null' => true,
                'comment' => 'Geschaeftsfuehrer / Inhaber'
            ]],
            'instances_companyRegNumber' => ['type' => 'string', 'opts' => [
                'limit' => 60, 'null' => true,
                'comment' => 'Handelsregisternummer (z.B. HRB 12345)'
            ]],
            'instances_locale' => ['type' => 'string', 'opts' => [
                'limit' => 10, 'default' => 'de_DE',
                'comment' => 'Spracheinstellung (de_DE, en_GB, ...)'
            ]],
        ];

        foreach ($instanceCols as $colName => $def) {
            if (!$instances->hasColumn($colName)) {
                $instances->addColumn($colName, $def['type'], $def['opts']);
            }
        }
        $instances->update();

        // --- clients: add tax ID and customer number ---
        $clients = $this->table('clients');
        $clientCols = [
            'clients_vatId' => ['type' => 'string', 'opts' => [
                'limit' => 30, 'null' => true,
                'comment' => 'USt-IdNr. des Kunden'
            ]],
            'clients_customerNumber' => ['type' => 'string', 'opts' => [
                'limit' => 30, 'null' => true,
                'comment' => 'Kundennummer'
            ]],
            'clients_paymentTermDays' => ['type' => 'integer', 'opts' => [
                'null' => true,
                'comment' => 'Individuelles Zahlungsziel (NULL = Firmen-Standard)'
            ]],
        ];

        foreach ($clientCols as $colName => $def) {
            if (!$clients->hasColumn($colName)) {
                $clients->addColumn($colName, $def['type'], $def['opts']);
            }
        }
        $clients->update();

        // --- projects: add Leistungszeitraum fields for GoBD ---
        $projects = $this->table('projects');
        if (!$projects->hasColumn('projects_deliveryNotes')) {
            $projects
                ->addColumn('projects_deliveryNotes', 'text', [
                    'null' => true,
                    'comment' => 'Lieferschein-Notizen'
                ])
                ->update();
        }
    }
}
