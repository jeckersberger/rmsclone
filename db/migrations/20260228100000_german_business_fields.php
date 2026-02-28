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
        $instances
            ->addColumn('instances_taxNumber', 'string', [
                'limit' => 60, 'null' => true, 'after' => 'instances_website',
                'comment' => 'Steuernummer (z.B. 123/456/78901)'
            ])
            ->addColumn('instances_vatId', 'string', [
                'limit' => 30, 'null' => true, 'after' => 'instances_taxNumber',
                'comment' => 'USt-IdNr. (z.B. DE123456789)'
            ])
            ->addColumn('instances_kurEnabled', 'boolean', [
                'default' => 1, 'after' => 'instances_vatId',
                'comment' => 'Kleinunternehmerregelung aktiv (§19 UStG)'
            ])
            ->addColumn('instances_vatRate', 'decimal', [
                'precision' => 5, 'scale' => 2, 'default' => 19.00,
                'after' => 'instances_kurEnabled',
                'comment' => 'Standard-MwSt-Satz in Prozent'
            ])
            ->addColumn('instances_bankName', 'string', [
                'limit' => 120, 'null' => true, 'after' => 'instances_vatRate',
                'comment' => 'Name der Bank'
            ])
            ->addColumn('instances_bankIban', 'string', [
                'limit' => 34, 'null' => true, 'after' => 'instances_bankName',
                'comment' => 'IBAN'
            ])
            ->addColumn('instances_bankBic', 'string', [
                'limit' => 11, 'null' => true, 'after' => 'instances_bankIban',
                'comment' => 'BIC/SWIFT'
            ])
            ->addColumn('instances_paymentTermDays', 'integer', [
                'default' => 14, 'after' => 'instances_bankBic',
                'comment' => 'Standard-Zahlungsziel in Tagen'
            ])
            ->addColumn('instances_courtOfJurisdiction', 'string', [
                'limit' => 120, 'null' => true, 'after' => 'instances_paymentTermDays',
                'comment' => 'Gerichtsstand'
            ])
            ->addColumn('instances_ceoName', 'string', [
                'limit' => 200, 'null' => true, 'after' => 'instances_courtOfJurisdiction',
                'comment' => 'Geschaeftsfuehrer / Inhaber'
            ])
            ->addColumn('instances_companyRegNumber', 'string', [
                'limit' => 60, 'null' => true, 'after' => 'instances_ceoName',
                'comment' => 'Handelsregisternummer (z.B. HRB 12345)'
            ])
            ->addColumn('instances_locale', 'string', [
                'limit' => 10, 'default' => 'de_DE', 'after' => 'instances_companyRegNumber',
                'comment' => 'Spracheinstellung (de_DE, en_GB, ...)'
            ])
            ->update();

        // --- clients: add tax ID and customer number ---
        $clients = $this->table('clients');
        $clients
            ->addColumn('clients_vatId', 'string', [
                'limit' => 30, 'null' => true, 'after' => 'clients_website',
                'comment' => 'USt-IdNr. des Kunden'
            ])
            ->addColumn('clients_customerNumber', 'string', [
                'limit' => 30, 'null' => true, 'after' => 'clients_vatId',
                'comment' => 'Kundennummer'
            ])
            ->addColumn('clients_paymentTermDays', 'integer', [
                'null' => true, 'after' => 'clients_customerNumber',
                'comment' => 'Individuelles Zahlungsziel (NULL = Firmen-Standard)'
            ])
            ->update();

        // --- projects: add Leistungszeitraum fields for GoBD ---
        $projects = $this->table('projects');
        $projects
            ->addColumn('projects_deliveryNotes', 'text', [
                'null' => true, 'after' => 'projects_invoiceNotes',
                'comment' => 'Lieferschein-Notizen'
            ])
            ->update();
    }
}
