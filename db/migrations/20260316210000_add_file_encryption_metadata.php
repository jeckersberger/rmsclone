<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

/**
 * Add encryption metadata columns to s3files table
 *
 * Adds support for at-rest file encryption using AES-256-GCM:
 * - s3files_encrypted: Boolean flag indicating file is encrypted
 * - s3files_integrity_hash: SHA-256 hash of original plaintext (for GoBD compliance)
 */
final class AddFileEncryptionMetadata extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('s3files');

        // Flag to indicate file is encrypted
        if (!$table->hasColumn('s3files_encrypted')) {
            $table->addColumn('s3files_encrypted', 'boolean', [
                'null' => false,
                'default' => 0,
                'limit' => MysqlAdapter::INT_TINY,
                'comment' => 'Whether the file is encrypted with AES-256-GCM at rest',
                'after' => 's3files_meta_public',
            ]);
        }

        // SHA-256 integrity hash of the original plaintext (before encryption)
        // Used for GoBD (German accounting) compliance verification
        if (!$table->hasColumn('s3files_integrity_hash')) {
            $table->addColumn('s3files_integrity_hash', 'string', [
                'null' => true,
                'limit' => 64,
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => 'SHA-256 hash of original file (before encryption) for GoBD integrity verification',
                'after' => 's3files_encrypted',
            ]);
        }

        $table->update();
    }
}
