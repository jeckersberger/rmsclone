<?php
/**
 * Phase 2 Migration: Workflow, Mahnwesen, Zahlungsbedingungen, Erweiterte Kunden,
 * Verfuegbarkeit, DATEV-Export, EUER
 */
use Phinx\Migration\AbstractMigration;

class Phase2WorkflowAndDunning extends AbstractMigration
{
    public function up()
    {
        // ═══════ 1) ANGEBOT → AUFTRAG → RECHNUNG WORKFLOW ═══════
        // Document lifecycle tracking
        if (!$this->hasTable('document_lifecycle')) {
            $this->table('document_lifecycle', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('projects_id', 'integer')
                ->addColumn('doc_type', 'string', ['limit' => 20, 'comment' => 'quote, order_confirmation, invoice, credit_note, cancellation'])
                ->addColumn('doc_number', 'string', ['limit' => 50])
                ->addColumn('status', 'string', ['limit' => 30, 'default' => 'draft', 'comment' => 'draft, sent, accepted, rejected, cancelled, paid, overdue, reminded'])
                ->addColumn('parent_doc_id', 'integer', ['null' => true, 'comment' => 'Links quote->order->invoice chain'])
                ->addColumn('s3files_id', 'integer', ['null' => true])
                ->addColumn('document_exports_id', 'integer', ['null' => true, 'comment' => 'Link to document_exports for GoBD'])
                ->addColumn('net_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
                ->addColumn('gross_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
                ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'EUR'])
                ->addColumn('valid_until', 'date', ['null' => true, 'comment' => 'Quote validity date'])
                ->addColumn('due_date', 'date', ['null' => true, 'comment' => 'Invoice payment due date'])
                ->addColumn('paid_date', 'date', ['null' => true])
                ->addColumn('paid_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
                ->addColumn('notes', 'text', ['null' => true])
                ->addColumn('sent_at', 'datetime', ['null' => true])
                ->addColumn('sent_to_email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'doc_type', 'status'])
                ->addIndex(['projects_id'])
                ->addIndex(['parent_doc_id'])
                ->addIndex(['due_date'])
                ->create();
        }

        // Status history for documents
        if (!$this->hasTable('document_status_history')) {
            $this->table('document_status_history', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('document_lifecycle_id', 'integer')
                ->addColumn('old_status', 'string', ['limit' => 30, 'null' => true])
                ->addColumn('new_status', 'string', ['limit' => 30])
                ->addColumn('comment', 'text', ['null' => true])
                ->addColumn('changed_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['document_lifecycle_id'])
                ->create();
        }

        // ═══════ 2) MAHNWESEN (DUNNING) ═══════
        if (!$this->hasTable('dunning_levels')) {
            $this->table('dunning_levels', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('level', 'integer', ['comment' => '0=Zahlungserinnerung, 1=1. Mahnung, 2=2. Mahnung, 3=3. Mahnung'])
                ->addColumn('name', 'string', ['limit' => 100, 'comment' => 'e.g. Zahlungserinnerung, 1. Mahnung'])
                ->addColumn('days_after_due', 'integer', ['comment' => 'Days after invoice due date'])
                ->addColumn('fee', 'decimal', ['precision' => 8, 'scale' => 2, 'default' => 0, 'comment' => 'Mahngebuehr'])
                ->addColumn('interest_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0, 'comment' => 'Verzugszinsen in %'])
                ->addColumn('email_subject', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('email_body', 'text', ['null' => true])
                ->addColumn('letter_template_id', 'integer', ['null' => true, 'comment' => 'document_templates.id for PDF'])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'level'], ['unique' => true])
                ->create();
        }

        if (!$this->hasTable('dunning_history')) {
            $this->table('dunning_history', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('document_lifecycle_id', 'integer', ['comment' => 'The overdue invoice'])
                ->addColumn('dunning_level_id', 'integer')
                ->addColumn('dunning_level', 'integer')
                ->addColumn('dunning_date', 'date')
                ->addColumn('fee_amount', 'decimal', ['precision' => 8, 'scale' => 2, 'default' => 0])
                ->addColumn('interest_amount', 'decimal', ['precision' => 8, 'scale' => 2, 'default' => 0])
                ->addColumn('total_due', 'decimal', ['precision' => 12, 'scale' => 2])
                ->addColumn('sent_at', 'datetime', ['null' => true])
                ->addColumn('sent_to_email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('s3files_id', 'integer', ['null' => true, 'comment' => 'PDF of dunning letter'])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'document_lifecycle_id'])
                ->create();
        }

        // ═══════ 3) ZAHLUNGSBEDINGUNGEN & SKONTO ═══════
        // Add skonto fields to instances
        if ($this->hasTable('instances')) {
            $table = $this->table('instances');
            if (!$table->hasColumn('instances_skontoRate')) {
                $table->addColumn('instances_skontoRate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0, 'null' => true, 'after' => 'instances_paymentTermDays'])
                      ->addColumn('instances_skontoDays', 'integer', ['default' => 0, 'null' => true, 'after' => 'instances_skontoRate'])
                      ->update();
            }
        }
        // Add skonto fields to clients (override per client)
        if ($this->hasTable('clients')) {
            $table = $this->table('clients');
            if (!$table->hasColumn('clients_skontoRate')) {
                $table->addColumn('clients_skontoRate', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true, 'after' => 'clients_paymentTermDays'])
                      ->addColumn('clients_skontoDays', 'integer', ['null' => true, 'after' => 'clients_skontoRate'])
                      ->update();
            }
        }

        // ═══════ 4) ERWEITERTE KUNDENVERWALTUNG ═══════
        // Contact persons (multiple per client)
        if (!$this->hasTable('client_contacts')) {
            $this->table('client_contacts', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('clients_id', 'integer')
                ->addColumn('contact_name', 'string', ['limit' => 255])
                ->addColumn('contact_role', 'string', ['limit' => 100, 'null' => true, 'comment' => 'Funktion/Position'])
                ->addColumn('contact_email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('contact_phone', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('contact_mobile', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('contact_notes', 'text', ['null' => true])
                ->addColumn('is_primary', 'boolean', ['default' => false])
                ->addColumn('is_invoice_recipient', 'boolean', ['default' => false])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addIndex(['clients_id'])
                ->create();
        }

        // Client tags
        if (!$this->hasTable('client_tags')) {
            $this->table('client_tags', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('tag_name', 'string', ['limit' => 100])
                ->addColumn('tag_color', 'string', ['limit' => 7, 'default' => '#007bff'])
                ->addIndex(['instances_id'])
                ->create();
        }
        if (!$this->hasTable('client_tag_assignments')) {
            $this->table('client_tag_assignments', ['id' => false, 'primary_key' => ['clients_id', 'client_tags_id'], 'engine' => 'InnoDB'])
                ->addColumn('clients_id', 'integer', ['null' => false])
                ->addColumn('client_tags_id', 'integer', ['null' => false])
                ->create();
        }

        // Add credit limit to clients
        if ($this->hasTable('clients')) {
            $table = $this->table('clients');
            if (!$table->hasColumn('clients_creditLimit')) {
                $table->addColumn('clients_creditLimit', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true, 'after' => 'clients_skontoDays'])
                      ->update();
            }
        }

        // ═══════ 5) VERFUEGBARKEITSKALENDER + KOLLISIONSERKENNUNG ═══════
        // Asset availability blocks (manual blocks like maintenance, reserved, etc.)
        if (!$this->hasTable('asset_availability_blocks')) {
            $this->table('asset_availability_blocks', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('assets_id', 'integer')
                ->addColumn('instances_id', 'integer')
                ->addColumn('block_type', 'string', ['limit' => 30, 'comment' => 'maintenance, reserved, unavailable, other'])
                ->addColumn('block_start', 'datetime')
                ->addColumn('block_end', 'datetime')
                ->addColumn('reason', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('deleted', 'boolean', ['default' => false])
                ->addIndex(['assets_id', 'block_start', 'block_end'])
                ->addIndex(['instances_id'])
                ->create();
        }

        // ═══════ 6) DATEV EXPORT ═══════
        if (!$this->hasTable('datev_exports')) {
            $this->table('datev_exports', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('export_type', 'string', ['limit' => 30, 'comment' => 'buchungen, stammdaten'])
                ->addColumn('period_from', 'date')
                ->addColumn('period_to', 'date')
                ->addColumn('filename', 'string', ['limit' => 255])
                ->addColumn('s3files_id', 'integer', ['null' => true])
                ->addColumn('record_count', 'integer', ['default' => 0])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id'])
                ->create();
        }

        // DATEV account mapping
        if (!$this->hasTable('datev_account_mapping')) {
            $this->table('datev_account_mapping', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('account_type', 'string', ['limit' => 50, 'comment' => 'revenue, expense_equipment, expense_staff, expense_subhire, receivable, bank, vat_collected, vat_paid'])
                ->addColumn('account_number', 'string', ['limit' => 10, 'comment' => 'DATEV Kontonummer (SKR03/SKR04)'])
                ->addColumn('account_name', 'string', ['limit' => 100])
                ->addColumn('tax_key', 'string', ['limit' => 10, 'null' => true, 'comment' => 'DATEV Steuerschluessel'])
                ->addIndex(['instances_id', 'account_type'], ['unique' => true])
                ->create();
        }

        // Add DATEV settings to instances
        if ($this->hasTable('instances')) {
            $table = $this->table('instances');
            if (!$table->hasColumn('instances_datevBeraternr')) {
                $table->addColumn('instances_datevBeraternr', 'string', ['limit' => 10, 'null' => true])
                      ->addColumn('instances_datevMandantennr', 'string', ['limit' => 10, 'null' => true])
                      ->addColumn('instances_datevKontenrahmen', 'string', ['limit' => 10, 'null' => true, 'default' => 'SKR03', 'comment' => 'SKR03 or SKR04'])
                      ->addColumn('instances_datevWjBeginn', 'date', ['null' => true, 'comment' => 'Wirtschaftsjahr Beginn'])
                      ->update();
            }
        }

        // ═══════ 7) EUER (Einnahmenueberschussrechnung) ═══════
        if (!$this->hasTable('euer_categories')) {
            $this->table('euer_categories', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('category_type', 'string', ['limit' => 20, 'comment' => 'income, expense'])
                ->addColumn('euer_line', 'string', ['limit' => 10, 'comment' => 'EUeR Formular-Zeile (z.B. 14, 51)'])
                ->addColumn('name', 'string', ['limit' => 150])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('sort_order', 'integer', ['default' => 0])
                ->addIndex(['instances_id', 'category_type'])
                ->create();
        }

        if (!$this->hasTable('euer_bookings')) {
            $this->table('euer_bookings', ['id' => 'id', 'engine' => 'InnoDB'])
                ->addColumn('instances_id', 'integer')
                ->addColumn('euer_categories_id', 'integer')
                ->addColumn('booking_date', 'date')
                ->addColumn('description', 'string', ['limit' => 255])
                ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2])
                ->addColumn('vat_amount', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0])
                ->addColumn('document_lifecycle_id', 'integer', ['null' => true, 'comment' => 'Link to invoice/payment'])
                ->addColumn('projects_id', 'integer', ['null' => true])
                ->addColumn('receipt_s3files_id', 'integer', ['null' => true, 'comment' => 'Beleg-Anhang'])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['instances_id', 'booking_date'])
                ->addIndex(['euer_categories_id'])
                ->create();
        }

        // Insert default dunning levels
        $this->execute("
            INSERT IGNORE INTO dunning_levels (instances_id, level, name, days_after_due, fee, interest_rate, email_subject, email_body)
            SELECT instances_id, 0, 'Zahlungserinnerung', 7, 0.00, 0.00,
                   'Zahlungserinnerung - {doc_number}',
                   'Sehr geehrte Damen und Herren,\n\nbei der Pruefung unserer Konten ist uns aufgefallen, dass die Rechnung {doc_number} vom {doc_date} ueber {gross_amount} EUR noch nicht beglichen wurde.\n\nWir bitten Sie, den Betrag innerhalb der naechsten 7 Tage zu ueberweisen.\n\nMit freundlichen Gruessen'
            FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO dunning_levels (instances_id, level, name, days_after_due, fee, interest_rate, email_subject, email_body)
            SELECT instances_id, 1, '1. Mahnung', 21, 0.00, 0.00,
                   '1. Mahnung - {doc_number}',
                   'Sehr geehrte Damen und Herren,\n\ntrotz unserer Zahlungserinnerung konnten wir fuer die Rechnung {doc_number} vom {doc_date} ueber {gross_amount} EUR noch keinen Zahlungseingang feststellen.\n\nWir bitten Sie dringend, den offenen Betrag innerhalb von 10 Tagen zu ueberweisen.\n\nMit freundlichen Gruessen'
            FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO dunning_levels (instances_id, level, name, days_after_due, fee, interest_rate, email_subject, email_body)
            SELECT instances_id, 2, '2. Mahnung', 35, 5.00, 0.00,
                   '2. Mahnung - {doc_number}',
                   'Sehr geehrte Damen und Herren,\n\nleider konnten wir trotz mehrfacher Erinnerung keinen Zahlungseingang fuer die Rechnung {doc_number} feststellen.\n\nWir muessen Ihnen daher eine Mahngebuehr von {fee_amount} EUR berechnen.\n\nBitte ueberweisen Sie den Gesamtbetrag von {total_due} EUR umgehend.\n\nMit freundlichen Gruessen'
            FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO dunning_levels (instances_id, level, name, days_after_due, fee, interest_rate, email_subject, email_body)
            SELECT instances_id, 3, 'Letzte Mahnung', 49, 10.00, 5.00,
                   'Letzte Mahnung vor gerichtlichem Mahnverfahren - {doc_number}',
                   'Sehr geehrte Damen und Herren,\n\ndie Rechnung {doc_number} vom {doc_date} ist seit {days_overdue} Tagen ueberfaellig.\n\nWir fordern Sie hiermit letztmalig auf, den Gesamtbetrag von {total_due} EUR (inkl. Mahngebuehr {fee_amount} EUR und Verzugszinsen {interest_amount} EUR) innerhalb von 7 Tagen zu begleichen.\n\nSollte bis dahin kein Zahlungseingang erfolgen, werden wir ohne weitere Ankuendigung das gerichtliche Mahnverfahren einleiten.\n\nMit freundlichen Gruessen'
            FROM instances WHERE instances_deleted = 0
        ");

        // Insert default DATEV account mappings (SKR03)
        $this->execute("
            INSERT IGNORE INTO datev_account_mapping (instances_id, account_type, account_number, account_name, tax_key)
            SELECT instances_id, 'revenue', '8400', 'Erloese 19% USt', '3'
            FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO datev_account_mapping (instances_id, account_type, account_number, account_name, tax_key)
            SELECT instances_id, 'revenue_kur', '8195', 'Erloese Kleinunternehmer §19 UStG', '0'
            FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO datev_account_mapping (instances_id, account_type, account_number, account_name, tax_key)
            SELECT instances_id, 'receivable', '1400', 'Forderungen aus Lieferungen und Leistungen', NULL
            FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO datev_account_mapping (instances_id, account_type, account_number, account_name, tax_key)
            SELECT instances_id, 'bank', '1200', 'Bank', NULL
            FROM instances WHERE instances_deleted = 0
        ");

        // Insert default EUeR categories
        $this->execute("
            INSERT IGNORE INTO euer_categories (instances_id, category_type, euer_line, name, sort_order)
            SELECT instances_id, 'income', '14', 'Betriebseinnahmen als Kleinunternehmer (§ 19 UStG)', 1 FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO euer_categories (instances_id, category_type, euer_line, name, sort_order)
            SELECT instances_id, 'expense', '51', 'Bezogene Leistungen (Fremdpersonal)', 10 FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO euer_categories (instances_id, category_type, euer_line, name, sort_order)
            SELECT instances_id, 'expense', '52', 'Ausgaben fuer eigenes Personal', 11 FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO euer_categories (instances_id, category_type, euer_line, name, sort_order)
            SELECT instances_id, 'expense', '54', 'Fahrzeugkosten und andere Fahrtkosten', 12 FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO euer_categories (instances_id, category_type, euer_line, name, sort_order)
            SELECT instances_id, 'expense', '56', 'Raumkosten und sonstige Grundstuecksaufwendungen', 13 FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO euer_categories (instances_id, category_type, euer_line, name, sort_order)
            SELECT instances_id, 'expense', '62', 'Sonstige unbeschraenkt abziehbare Betriebsausgaben', 14 FROM instances WHERE instances_deleted = 0
        ");
        $this->execute("
            INSERT IGNORE INTO euer_categories (instances_id, category_type, euer_line, name, sort_order)
            SELECT instances_id, 'expense', '64', 'Abschreibungen auf Anlagevermoegen', 15 FROM instances WHERE instances_deleted = 0
        ");
    }

    public function down()
    {
        // Drop in reverse order
        $this->table('euer_bookings')->drop()->save();
        $this->table('euer_categories')->drop()->save();
        $this->table('datev_exports')->drop()->save();
        $this->table('datev_account_mapping')->drop()->save();
        $this->table('asset_availability_blocks')->drop()->save();
        $this->table('client_tag_assignments')->drop()->save();
        $this->table('client_tags')->drop()->save();
        $this->table('client_contacts')->drop()->save();
        $this->table('dunning_history')->drop()->save();
        $this->table('dunning_levels')->drop()->save();
        $this->table('document_status_history')->drop()->save();
        $this->table('document_lifecycle')->drop()->save();

        // Remove added columns
        if ($this->hasTable('instances')) {
            $table = $this->table('instances');
            foreach (['instances_skontoRate', 'instances_skontoDays', 'instances_datevBeraternr', 'instances_datevMandantennr', 'instances_datevKontenrahmen', 'instances_datevWjBeginn'] as $col) {
                if ($table->hasColumn($col)) $table->removeColumn($col);
            }
            $table->update();
        }
        if ($this->hasTable('clients')) {
            $table = $this->table('clients');
            foreach (['clients_skontoRate', 'clients_skontoDays', 'clients_creditLimit'] as $col) {
                if ($table->hasColumn($col)) $table->removeColumn($col);
            }
            $table->update();
        }
    }
}
