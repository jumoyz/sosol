<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use DateTimeImmutable;
use DateInterval;

/**
 * NOTE:
 * - This file assumes you already have a global function `getDbConnection(): PDO`
 *   from config.php. We wrap it in DB::pdo() to keep a clean API.
 * - Session should be started by your front controller when needed.
 */

//////////////////////////
// Support: Infrastructure
//////////////////////////

namespace App\Support;

use PDO;
use DateTimeImmutable;
use DateInterval;

final class Env
{
    public static function bool(string $key, bool $default = false): bool
    {
        $val = getenv($key);
        if ($val === false) return $default;
        return in_array(strtolower((string)$val), ['1','true','yes','on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $val = getenv($key);
        if ($val === false || !is_numeric($val)) return $default;
        return (int)$val;
    }

    public static function str(string $key, ?string $default = null): ?string
    {
        $val = getenv($key);
        return $val === false ? $default : $val;
    }
}

final class DB
{
    /** @return PDO */
    public static function pdo(): PDO
    {
        // Use your existing config helper
        /** @var callable $fn */
        $fn = 'getDbConnection';
        if (function_exists($fn)) {
            return $fn();
        }
        throw new \RuntimeException('getDbConnection() not found.');
    }
}

final class Logger
{
    public static function error(string $message, string $level = 'ERROR'): void
    {
        $useSyslog = Env::bool('USE_SYSLOG', false);

        if ($useSyslog) {
            openlog('SoSol', LOG_PID, LOG_LOCAL0);
            syslog(LOG_ERR, sprintf('[%s] %s', $level, $message));
            closelog();
            return;
        }

        $logDir = \defined('ROOT_PATH') ? ROOT_PATH . '/logs' : __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = rtrim($logDir, '/') . '/error-' . date('Y-m-d') . '.log';
        $entry = '[' . date('Y-m-d H:i:s') . "] [$level] $message" . PHP_EOL;
        error_log($entry, 3, $logFile);
    }
}

final class Flash
{
    private const KEY = 'flash_messages';

    public static function add(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $_SESSION[self::KEY][$type][] = $message;
    }

    /** @return array<string, string[]> */
    public static function pull(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);
        return $messages;
    }
}

final class CSRF
{
    private const KEY = 'csrf_token';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function validate(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        return is_string($token) && hash_equals($_SESSION[self::KEY] ?? '', $token);
    }
}

final class Dates
{
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }

    public static function addDays(DateTimeImmutable $dt, int $days): DateTimeImmutable
    {
        return $dt->add(new DateInterval('P' . $days . 'D'));
    }

    public static function format(DateTimeImmutable $dt, string $format = 'Y-m-d H:i:s'): string
    {
        return $dt->format($format);
    }

    public static function timeAgo(DateTimeImmutable $dt): string
    {
        $diff = self::now()->getTimestamp() - $dt->getTimestamp();
        if ($diff < 60)   return $diff . ' seconds ago';
        if ($diff < 3600) return (int)round($diff / 60) . ' minutes ago';
        if ($diff < 86400) return (int)round($diff / 3600) . ' hours ago';
        if ($diff < 604800) return (int)round($diff / 86400) . ' days ago';
        if ($diff < 2592000) return (int)round($diff / 604800) . ' weeks ago';
        if ($diff < 31536000) return (int)round($diff / 2592000) . ' months ago';
        return (int)round($diff / 31536000) . ' years ago';
    }
}

final class Files
{
    /**
     * Validate file upload
     * @param array $file One entry from $_FILES
     * @param array<string>|null $allowedTypes
     * @param int|null $maxSize
     * @return array{status:bool,message:string,fileExtension?:string}
     */
    public static function validate(array $file, ?array $allowedTypes = null, ?int $maxSize = null): array
    {
        $allowedTypes ??= array_filter(array_map('trim', explode(',', (string)Env::str('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf'))));
        $maxSize = (int)($maxSize ?? Env::int('UPLOAD_MAX_SIZE', 5 * 1024 * 1024)); // default 5MB

        if (!isset($file['error']) || is_array($file['error'])) {
            return ['status' => false, 'message' => 'Invalid file parameters'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK: break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: return ['status' => false, 'message' => 'File too large'];
            case UPLOAD_ERR_PARTIAL:   return ['status' => false, 'message' => 'Partial upload'];
            case UPLOAD_ERR_NO_FILE:   return ['status' => false, 'message' => 'No file uploaded'];
            case UPLOAD_ERR_NO_TMP_DIR:return ['status' => false, 'message' => 'Missing temp folder'];
            case UPLOAD_ERR_CANT_WRITE:return ['status' => false, 'message' => 'Disk write failed'];
            case UPLOAD_ERR_EXTENSION: return ['status' => false, 'message' => 'Upload blocked by extension'];
            default:                   return ['status' => false, 'message' => 'Unknown upload error'];
        }

        if ((int)$file['size'] > $maxSize) {
            return ['status' => false, 'message' => 'File is too large (max ' . self::formatSize($maxSize) . ')'];
        }

        $ext = strtolower((string)pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes, true)) {
            return ['status' => false, 'message' => 'File type not allowed: ' . $ext];
        }

        return ['status' => true, 'message' => '', 'fileExtension' => $ext];
    }

    public static function formatSize(int $bytes): string
    {
        $units = ['B','KB','MB','GB','TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

final class Util
{
    public static function sanitize(string $data): string
    {
        return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    public static function redirect(string $url): never
    {
        if (!headers_sent()) {
            header("Location: $url");
            exit;
        }
        $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        echo '<script>window.location.href="' . $safe . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safe . '"></noscript>';
        echo '<p>Please click <a href="' . $safe . '">here</a> if you are not redirected.</p>';
        exit;
    }

    public static function uuidV4(): string
    {
        $data = random_bytes(16);
        // Set version to 0100
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function formatCurrency(float $amount, string $currency = 'HTG'): string
    {
        $map = [
            'HTG' => ['symbol' => 'G',  'decimals' => 2, 'pos' => 'after'],
            'USD' => ['symbol' => '$',  'decimals' => 2, 'pos' => 'before'],
        ];
        $cfg = $map[$currency] ?? $map['HTG'];
        $num = number_format($amount, $cfg['decimals'], '.', ',');
        return $cfg['pos'] === 'before' ? $cfg['symbol'] . $num : $num . ' ' . $cfg['symbol'];
    }
}

/////////////////////////////
// Auth & Domain: User/Wallet
/////////////////////////////

namespace App\Auth;

use App\Support\DB;
use App\Support\Flash;

final class User
{
    /** Combine login + role checks in one place */
    public static function requireLogin(?string $redirect = null): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!isset($_SESSION['user_id'])) {
            $to = $redirect ?? ($_SERVER['REQUEST_URI'] ?? '/');
            Flash::add('error', 'You must be logged in.');
            header('Location: ?page=login&redirect=' . urlencode($to));
            exit;
        }
    }

    public static function isLoggedIn(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] !== '';
    }

    public static function id(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        return $_SESSION['user_role'] ?? null;
    }

    /** @param string[]|string $roles */
    public static function hasRole(array $roles): bool
    {
        if (!self::isLoggedIn()) return false;
        // $roles is already an array due to type hint
        return in_array(self::role(), $roles, true);
    }

    public static function isAdmin(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $flag = $_SESSION['is_admin'] ?? false;
        return self::hasRole(['admin', 'super_admin']) && $flag === true;
    }
}

namespace App\Finance;

use App\Support\DB;
use App\Support\Logger;
use App\Support\Dates;
use App\Support\Util;
use PDO;

final class Wallet
{
    /**
     * Get or create wallet for a user.
     * Supports BOTH schemas:
     *   A) Legacy: wallets(user_id, balance_htg, balance_usd)
     *   B) New:   wallets(user_id, currency CHAR(3), balance DECIMAL, UNIQUE(user_id,currency))
     *
     * @return array<string,mixed>
     */
    /**
     * @param string|int $userId
     */
    public static function getOrCreate($userId, string $currency = 'HTG'): array
    {
        $pdo = DB::pdo();

        // Try new schema first
        try {
            $stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ? AND currency = ? LIMIT 1");
            $stmt->execute([$userId, $currency]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;

            // Create new-currency wallet
            $id = Util::uuidV4();
            $now = Dates::format(Dates::now());
            $ins = $pdo->prepare("
                INSERT INTO wallets (id, user_id, currency, balance, created_at, updated_at)
                VALUES (?, ?, ?, 0.00, ?, ?)
            ");
            $ins->execute([$id, $userId, $currency, $now, $now]);

            return [
                'id' => $id,
                'user_id' => $userId,
                'currency' => $currency,
                'balance' => '0.00',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        } catch (\Throwable $e) {
            // Fallback to legacy schema
            try {
                $stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;

                $now = Dates::format(Dates::now());
                $pdo->prepare("INSERT INTO wallets (user_id, balance_htg, balance_usd, created_at, updated_at) VALUES (?, 0, 0, ?, ?)")
                    ->execute([$userId, $now, $now]);

                return [
                    'user_id' => $userId,
                    'balance_htg' => 0,
                    'balance_usd' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } catch (\Throwable $ex) {
                Logger::error('Wallet getOrCreate failed: ' . $ex->getMessage());
                return [];
            }
        }
    }

    /**
     * Get balances (returns both legacy & new style when possible)
     * @param string|int $userId
     * @return array{HTG?:float,USD?:float,by_currency?:array<string,float>}
     */
    public static function balances($userId): array
    {
        $pdo = DB::pdo();
        $out = ['by_currency' => []];

        // New schema multi-currency
        try {
            $stmt = $pdo->prepare("SELECT currency, balance FROM wallets WHERE user_id = ?");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                foreach ($rows as $r) {
                    $out['by_currency'][$r['currency']] = (float)$r['balance'];
                    if (in_array($r['currency'], ['HTG','USD'], true)) {
                        $out[$r['currency']] = (float)$r['balance'];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Legacy
            try {
                $stmt = $pdo->prepare("SELECT balance_htg, balance_usd FROM wallets WHERE user_id = ? LIMIT 1");
                $stmt->execute([$userId]);
                if ($w = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $out['HTG'] = (float)$w['balance_htg'];
                    $out['USD'] = (float)$w['balance_usd'];
                    $out['by_currency']['HTG'] = (float)$w['balance_htg'];
                    $out['by_currency']['USD'] = (float)$w['balance_usd'];
                }
            } catch (\Throwable $ex) {
                // ignore
            }
        }
        return $out;
    }
}

//////////////////////
// Domain: SOL Groups
//////////////////////

namespace App\Sol;

use App\Support\DB;
use App\Support\Dates;
use App\Support\Logger;
use App\Support\Util;
use PDO;
use DateTimeImmutable;

final class SolGroup
{
    public static function frequencyDays(string $frequency): int
    {
        switch ($frequency) {
            case 'daily':
                return 1;
            case 'every3days':
                return 3;
            case 'weekly':
                return 7;
            case 'biweekly':
                return 14;
            case 'monthly':
                return 30;
            default:
                return 30;
        }
    }

    /**
     * Fetch group by ID
     * @return array<string,mixed>|null
     * 
     */
    public static function fetch(string $groupId): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM sol_groups WHERE id = ? LIMIT 1");
        $stmt->execute([$groupId]);
        $g = $stmt->fetch(PDO::FETCH_ASSOC);
        return $g ?: null;
    }
    /**
     * @param string|int $userId
     */
    public static function isMember(string $groupId, $userId): bool
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT 1 FROM sol_participants WHERE sol_group_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$groupId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function nextPosition(string $groupId): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(payout_position),0) AS max_pos FROM sol_participants WHERE sol_group_id = ?");
        $stmt->execute([$groupId]);
        $max = (int)($stmt->fetch(PDO::FETCH_ASSOC)['max_pos'] ?? 0);
        return $max + 1;
    }

    /**
     * Join a group (no wallet deduction here)
     * @return bool
     */
    public static function join(string $groupId, $userId): bool
    {
        $pdo = DB::pdo();
        $group = self::fetch($groupId);
        if (!$group) return false;

        if (self::isMember($groupId, $userId)) {
            return true; // already member
        }

        $memberCount = (int)self::memberCount($groupId);
        if ($memberCount >= (int)$group['member_limit']) {
            return false;
        }

        $position = self::nextPosition($groupId);
        $baseDate = !empty($group['start_date']) ? new DateTimeImmutable($group['start_date']) : Dates::now();
        $due = Dates::addDays($baseDate, self::frequencyDays((string)$group['frequency']));

        $stmt = $pdo->prepare("
            INSERT INTO sol_participants
                (id, sol_group_id, user_id, role, join_date, payout_order, payout_position, contribution_status,
                 total_contributed, total_received, contribution_due_date, created_at, updated_at)
            VALUES
                (?, ?, ?, 'member', NOW(), ?, ?, 'pending', 0, 0, ?, NOW(), NOW())
        ");
        return $stmt->execute([
            Util::uuidV4(),
            $groupId,
            $userId,
            $position,
            $position,
            $due->format('Y-m-d')
        ]);
    }

    public static function memberCount(string $groupId): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sol_participants WHERE sol_group_id = ?");
        $stmt->execute([$groupId]);
        return (int)$stmt->fetchColumn();
    }
}

/////////////////////////
// Global error handlers
/////////////////////////

namespace App\Bootstrap;

use App\Support\Logger;

set_exception_handler(function (\Throwable $e): void {
    Logger::error('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'An internal error occurred.';
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    Logger::error(sprintf('PHP error [%d]: %s in %s:%d', $errno, $errstr, $errfile, $errline));
    // Return false to allow PHP internal handler to proceed for fatal errors
    return true;
});
