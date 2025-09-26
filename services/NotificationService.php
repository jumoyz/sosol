<?php
namespace App;

/**
 * Simple NotificationService stub.
 * For now records notifications into a table if it exists, otherwise logs.
 * Schema suggestion:
 *   CREATE TABLE notifications (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     user_id CHAR(36) NOT NULL,
 *     type VARCHAR(50) NOT NULL,
 *     title VARCHAR(150) NOT NULL,
 *     body TEXT NULL,
 *     read_at DATETIME NULL,
 *     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     KEY idx_notifications_user (user_id, read_at)
 *   );
 */
class NotificationService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function notify(string $userId, string $type, string $title, string $body = ''): void
    {
        // Attempt insert; fallback to log if table missing
        try {
            $stmt = $this->pdo->prepare('INSERT INTO notifications (user_id, type, title, body) VALUES (?,?,?,?)');
            $stmt->execute([$userId, $type, $title, $body]);
        } catch (\Throwable $e) {
            $this->log("NOTIFY_FALLBACK user=$userId type=$type title=$title err=" . $e->getMessage());
        }
    }

    private function log(string $message): void
    {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . date('Y-m-d') . '.log';
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
    }
}
