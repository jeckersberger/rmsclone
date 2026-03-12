<?php
/**
 * Auth Improvements - 2FA/TOTP, Login Log, Password Policy
 *
 * Fuegt TOTP-Felder zur users-Tabelle hinzu und erstellt die login_log-Tabelle.
 */
use Phinx\Migration\AbstractMigration;

class AuthImprovements extends AbstractMigration
{
    public function up()
    {
        // TOTP-Felder zur users-Tabelle hinzufuegen
        $usersTable = $this->table('users');
        if (!$usersTable->hasColumn('users_totpSecret')) {
            $usersTable
                ->addColumn('users_totpSecret', 'string', [
                    'null' => true,
                    'limit' => 32,
                    'after' => 'users_password',
                ])
                ->addColumn('users_totpEnabled', 'boolean', [
                    'null' => false,
                    'default' => 0,
                    'after' => 'users_totpSecret',
                ])
                ->addColumn('users_totpBackupCodes', 'text', [
                    'null' => true,
                    'after' => 'users_totpEnabled',
                ])
                ->update();
        }

        // Login-Log-Tabelle erstellen
        if (!$this->hasTable('login_log')) {
            $this->table('login_log', ['id' => false, 'primary_key' => ['id'], 'engine' => 'InnoDB'])
                ->addColumn('id', 'integer', ['identity' => true, 'signed' => true])
                ->addColumn('users_userid', 'integer', ['null' => true, 'signed' => true])
                ->addColumn('ip_address', 'string', ['limit' => 45])
                ->addColumn('user_agent', 'text', ['null' => true])
                ->addColumn('success', 'boolean', ['default' => 0])
                ->addColumn('failure_reason', 'string', ['null' => true, 'limit' => 100])
                ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['users_userid'])
                ->addIndex(['ip_address'])
                ->addIndex(['created_at'])
                ->addIndex(['success'])
                ->create();
        }
    }

    public function down()
    {
        $usersTable = $this->table('users');
        if ($usersTable->hasColumn('users_totpSecret')) {
            $usersTable
                ->removeColumn('users_totpSecret')
                ->removeColumn('users_totpEnabled')
                ->removeColumn('users_totpBackupCodes')
                ->update();
        }

        if ($this->hasTable('login_log')) {
            $this->table('login_log')->drop()->save();
        }
    }
}
