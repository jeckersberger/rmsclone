<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * E-Mail Inbox: Tabellen fuer eingehende E-Mails und Anhaenge.
 * Ermoeglicht IMAP-Anbindung mit flexibler Provider-Konfiguration.
 */
final class EmailInbox extends AbstractMigration
{
    public function change(): void
    {
        // Eingehende E-Mails
        $emailReceived = $this->table('emailReceived', [
            'id' => 'emailReceived_id',
            'signed' => false,
        ]);
        $emailReceived
            ->addColumn('instances_id', 'integer', ['signed' => false])
            ->addColumn('emailReceived_messageId', 'string', [
                'limit' => 255,
                'comment' => 'Message-ID Header der E-Mail',
            ])
            ->addColumn('emailReceived_fromEmail', 'string', [
                'limit' => 255,
                'comment' => 'Absender E-Mail-Adresse',
            ])
            ->addColumn('emailReceived_fromName', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Absender Name',
            ])
            ->addColumn('emailReceived_toEmail', 'string', [
                'limit' => 255,
                'comment' => 'Empfaenger E-Mail-Adresse',
            ])
            ->addColumn('emailReceived_subject', 'string', [
                'limit' => 1000,
                'null' => true,
                'comment' => 'Betreff',
            ])
            ->addColumn('emailReceived_bodyText', 'text', [
                'null' => true,
                'comment' => 'Plaintext-Inhalt',
            ])
            ->addColumn('emailReceived_bodyHtml', 'text', [
                'null' => true,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM,
                'comment' => 'HTML-Inhalt',
            ])
            ->addColumn('emailReceived_date', 'datetime', [
                'comment' => 'Datum der E-Mail (aus Header)',
            ])
            ->addColumn('emailReceived_fetchedAt', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'Zeitpunkt des IMAP-Abrufs',
            ])
            ->addColumn('emailReceived_isRead', 'boolean', [
                'default' => 0,
                'comment' => 'Wurde die E-Mail in der App gelesen?',
            ])
            ->addColumn('emailReceived_isProcessed', 'boolean', [
                'default' => 0,
                'comment' => 'Wurde die E-Mail automatisch verarbeitet (z.B. Rechnung erkannt)?',
            ])
            ->addColumn('emailReceived_folder', 'string', [
                'limit' => 100,
                'default' => 'INBOX',
                'comment' => 'IMAP-Ordner',
            ])
            ->addColumn('emailReceived_hasAttachments', 'boolean', [
                'default' => 0,
            ])
            ->addColumn('projects_id', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'Zugeordnetes Projekt (manuell oder automatisch)',
            ])
            ->addColumn('clients_id', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'Zugeordneter Kunde (automatisch anhand Absender)',
            ])
            ->addIndex(['instances_id'])
            ->addIndex(['emailReceived_messageId', 'instances_id'], ['unique' => true])
            ->addIndex(['emailReceived_fromEmail'])
            ->addIndex(['emailReceived_date'])
            ->addIndex(['emailReceived_isRead'])
            ->addIndex(['clients_id'])
            ->addIndex(['projects_id'])
            ->addForeignKey('instances_id', 'instances', 'instances_id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();

        // Anhaenge der eingehenden E-Mails
        $emailAttachment = $this->table('emailAttachments', [
            'id' => 'emailAttachment_id',
            'signed' => false,
        ]);
        $emailAttachment
            ->addColumn('emailReceived_id', 'integer', ['signed' => false])
            ->addColumn('instances_id', 'integer', ['signed' => false])
            ->addColumn('emailAttachment_filename', 'string', [
                'limit' => 500,
                'comment' => 'Original-Dateiname',
            ])
            ->addColumn('emailAttachment_mimeType', 'string', [
                'limit' => 100,
                'comment' => 'MIME-Type des Anhangs',
            ])
            ->addColumn('emailAttachment_size', 'integer', [
                'signed' => false,
                'comment' => 'Dateigroesse in Bytes',
            ])
            ->addColumn('emailAttachment_storagePath', 'string', [
                'limit' => 500,
                'comment' => 'Lokaler Speicherpfad',
            ])
            ->addColumn('emailAttachment_savedAt', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('s3files_id', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'Verknuepfung mit s3files wenn in Dateisystem importiert',
            ])
            ->addIndex(['emailReceived_id'])
            ->addIndex(['instances_id'])
            ->addForeignKey('emailReceived_id', 'emailReceived', 'emailReceived_id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('instances_id', 'instances', 'instances_id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}
