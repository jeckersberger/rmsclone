<?php
declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

/**
 * Adds quote_approval_tokens table for public quote approval via unique URL.
 * Customers can view, accept or reject quotes without logging in.
 */
final class QuoteApprovalTokens extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('quote_approval_tokens')) {
            $this->table('quote_approval_tokens', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('instances_id', 'integer')
                ->addColumn('projects_id', 'integer')
                ->addColumn('document_lifecycle_id', 'integer', ['comment' => 'Links to the quote in document_lifecycle'])
                ->addColumn('s3files_id', 'integer', ['null' => true, 'comment' => 'PDF file'])
                ->addColumn('token', 'string', ['limit' => 64, 'comment' => 'Unique URL token'])
                ->addColumn('client_name', 'string', ['limit' => 255])
                ->addColumn('client_email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('doc_number', 'string', ['limit' => 50])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending', 'comment' => 'pending, accepted, rejected'])
                ->addColumn('accepted_at', 'datetime', ['null' => true])
                ->addColumn('rejected_at', 'datetime', ['null' => true])
                ->addColumn('client_comment', 'text', ['null' => true, 'comment' => 'Customer feedback'])
                ->addColumn('client_signature_name', 'string', ['limit' => 255, 'null' => true, 'comment' => 'Typed signature'])
                ->addColumn('client_ip', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('expires_at', 'datetime', ['null' => true, 'comment' => 'Token expiry date'])
                ->addColumn('created_by', 'integer')
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['token'], ['unique' => true])
                ->addIndex(['instances_id', 'projects_id'])
                ->addIndex(['document_lifecycle_id'])
                ->create();
        }
    }
}
