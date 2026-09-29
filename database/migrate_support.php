<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require __DIR__ . '/../config/database.php';
$tables = [
    "CREATE TABLE IF NOT EXISTS conversations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
        status ENUM('open','closed') NOT NULL DEFAULT 'open', last_message_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_conversation_user(user_id), KEY ix_conversation_last_message(last_message_at),
        CONSTRAINT fk_conversation_user FOREIGN KEY(user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS messages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, conversation_id BIGINT UNSIGNED NOT NULL,
        sender_id INT UNSIGNED NOT NULL, message TEXT NULL, image_path VARCHAR(255) NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY ix_message_conversation(conversation_id,id), KEY ix_message_unread(conversation_id,is_read),
        CONSTRAINT fk_message_conversation FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON UPDATE CASCADE ON DELETE RESTRICT,
        CONSTRAINT fk_message_sender FOREIGN KEY(sender_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS notifications (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, recipient_role ENUM('admin','user') NOT NULL,
        recipient_id INT UNSIGNED NULL, notification_type VARCHAR(40) NOT NULL, message VARCHAR(255) NOT NULL,
        target_url VARCHAR(255) NOT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY ix_notification_recipient(recipient_role,recipient_id,is_read,created_at),
        CONSTRAINT fk_notification_user FOREIGN KEY(recipient_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS admin_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, admin_id INT UNSIGNED NOT NULL,
        action VARCHAR(60) NOT NULL, entity_type VARCHAR(40) NOT NULL, entity_id BIGINT UNSIGNED NULL,
        description VARCHAR(255) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY ix_admin_log_created(created_at),
        CONSTRAINT fk_admin_log_user FOREIGN KEY(admin_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($tables as $sql) $pdo->exec($sql);
echo "Support tables are ready (no existing rows changed).\n";
