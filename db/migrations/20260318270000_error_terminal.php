<?php

use Rms\Database\Migration;

class CreateErrorTerminalTable extends Migration
{
    public function up()
    {
        $this->db->rawQuery("
            CREATE TABLE IF NOT EXISTS system_error_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                level ENUM('debug','info','warning','error','critical') NOT NULL DEFAULT 'error',
                source VARCHAR(100) NOT NULL,
                message TEXT NOT NULL,
                stack_trace LONGTEXT NULL,
                context JSON NULL,
                url VARCHAR(500) NULL,
                user_id INT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                instances_id INT NOT NULL,
                is_resolved BOOLEAN NOT NULL DEFAULT FALSE,
                resolved_by INT NULL,
                resolved_at TIMESTAMP NULL,
                resolved_note TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_level_created (level, created_at),
                INDEX idx_instance_created (instances_id, created_at),
                INDEX idx_is_resolved (is_resolved),
                FOREIGN KEY (instances_id) REFERENCES instances(instances_id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(users_id) ON DELETE SET NULL,
                FOREIGN KEY (resolved_by) REFERENCES users(users_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down()
    {
        $this->db->rawQuery("DROP TABLE IF EXISTS system_error_log");
    }
}
