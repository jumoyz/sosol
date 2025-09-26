<?php
/**
 * Seed Script: Sample Investments & Interests
 * -------------------------------------------------------------
 * Purpose:
 *   Populate the investments feature with sample data for local/dev testing.
 *
 * Usage (CLI only recommended):
 *   php tools/seed-investments.php               # Normal (skips if already seeded)
 *   php tools/seed-investments.php --force       # Force reseed (will delete existing sample investments created by this script)
 *   php tools/seed-investments.php --with-interests # Add sample investor interests
 *   php tools/seed-investments.php --force --with-interests
 *
 * Safeguards:
 *   - Refuses to run if executed via web SAPI (to avoid accidental production runs).
 *   - Limits deletions to rows tagged with a special marker in description JSON fragment.
 *   - Idempotent unless --force supplied.
 *
 * Inserts:
 *   - 5 sample investments (mixed sectors, statuses draft/open/funded).
 *   - Optionally attaches interests for 2–4 investors each (when --with-interests set).
 *
 * Requirements:
 *   - Existing users table with at least 2 users; will auto-create a minimal placeholder user if needed.
 *   - Database connection via includes/config.php & getDbConnection() helper.
 */

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

// Root resolution
$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/functions.php';

$options = getopt('', ['force', 'with-interests']);
$force = array_key_exists('force', $options);
$withInterests = array_key_exists('with-interests', $options);

try {
    $db = getDbConnection();
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] Could not connect to DB: " . $e->getMessage() . "\n");
    exit(1);
}

$seedTag = '"seed_origin":"investments_mvp_seed"';

function fetchUsers(PDO $db): array {
    $stmt = $db->query("SELECT id, email, first_name, last_name FROM users ORDER BY created_at LIMIT 10");
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ensurePlaceholderUser(PDO $db): array {
    $stmt = $db->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
    $stmt->execute(['founder@example.local']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) return $user;

    $id = generateUuid();
    $now = date('Y-m-d H:i:s');
    $passwordHash = password_hash('Password123!', PASSWORD_BCRYPT);
    $insert = $db->prepare("INSERT INTO users (id, email, password, first_name, last_name, kyc_verified, created_at, updated_at) VALUES (?, ?, ?, 'Seed', 'Founder', 1, ?, ?)");
    $insert->execute([$id, 'founder@example.local', $passwordHash, $now, $now]);
    echo "Created placeholder founder user founder@example.local (pwd: Password123!)\n";
    return ['id' => $id, 'email' => 'founder@example.local'];
}

function alreadySeeded(PDO $db, string $seedTag): bool {
    $sql = "SELECT COUNT(*) FROM investments WHERE description LIKE ?";
    $stmt = $db->prepare($sql);
    $stmt->execute(['%' . $seedTag . '%']);
    return (int)$stmt->fetchColumn() > 0;
}

function deleteSeeded(PDO $db, string $seedTag): void {
    echo "Deleting previously seeded investments...\n";
    // Cascade interests by FK (ON DELETE CASCADE not defined? We'll manually delete interests first.)
    $sqlIds = "SELECT id FROM investments WHERE description LIKE ?";
    $stmt = $db->prepare($sqlIds);
    $stmt->execute(['%' . $seedTag . '%']);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if ($ids) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $delI = $db->prepare("DELETE FROM investment_interests WHERE investment_id IN ($in)");
        $delI->execute($ids);
        $delInv = $db->prepare("DELETE FROM investments WHERE id IN ($in)");
        $delInv->execute($ids);
        echo "Deleted " . count($ids) . " investments and related interests.\n";
    } else {
        echo "No prior seeded investments to delete.\n";
    }
}

if (alreadySeeded($db, $seedTag)) {
    if ($force) {
        deleteSeeded($db, $seedTag);
    } else {
        echo "Seed data appears to already exist. Use --force to reseed.\n";
        exit(0);
    }
}

$users = fetchUsers($db);
if (count($users) < 2) {
    $users[] = ensurePlaceholderUser($db);
    $users = fetchUsers($db); // refresh list
}

if (!$users) {
    fwrite(STDERR, "[ERROR] No users available to own investments.\n");
    exit(1);
}

// Helper to pick a user different from provided one
function pickDifferentUser(array $users, string $excludeId): ?array {
    $filtered = array_values(array_filter($users, fn($u) => $u['id'] !== $excludeId));
    return $filtered ? $filtered[array_rand($filtered)] : null;
}

$now = new DateTimeImmutable('now');
$future30 = $now->modify('+30 days')->format('Y-m-d');
$future45 = $now->modify('+45 days')->format('Y-m-d');
$future60 = $now->modify('+60 days')->format('Y-m-d');

$sampleInvestments = [
    [
        'title' => 'AgriTech Smart Irrigation Pilot',
        'sector' => 'Agriculture',
        'goal' => 15000,
        'equity' => 8.5,
        'end_date' => $future30,
        'status' => 'open',
        'visibility' => 'public',
        'desc' => 'Pilot deployment of IoT soil moisture sensors across 5 farms. {' . $seedTag . '}'
    ],
    [
        'title' => 'Community Solar Microgrid Expansion',
        'sector' => 'Energy',
        'goal' => 40000,
        'equity' => 12.0,
        'end_date' => $future60,
        'status' => 'open',
        'visibility' => 'public',
        'desc' => 'Scaling local microgrid to add battery storage & improve reliability. {' . $seedTag . '}'
    ],
    [
        'title' => 'Digital Health Outreach Platform',
        'sector' => 'Healthcare',
        'goal' => 25000,
        'equity' => 10.0,
        'end_date' => $future45,
        'status' => 'draft',
        'visibility' => 'private',
        'desc' => 'Mobile-first platform for rural telemedicine triage. {' . $seedTag . '}'
    ],
    [
        'title' => 'Artisan Marketplace Export Hub',
        'sector' => 'Commerce',
        'goal' => 18000,
        'equity' => 6.0,
        'end_date' => $future45,
        'status' => 'open',
        'visibility' => 'public',
        'desc' => 'Logistics + digital storefront enabling crafts export. {' . $seedTag . '}'
    ],
    [
        'title' => 'EdTech Remote STEM Labs',
        'sector' => 'Education',
        'goal' => 32000,
        'equity' => 9.5,
        'end_date' => $future60,
        'status' => 'open',
        'visibility' => 'public',
        'desc' => 'Blended learning kits + curriculum for remote learners. {' . $seedTag . '}'
    ],
];

$insertInv = $db->prepare("INSERT INTO investments (id, user_id, title, sector, funding_goal, amount_raised, equity_offered, pitch_deck, video_url, description, end_date, status, visibility, archived, sector_tags, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,0,NULL,NOW(),NOW())");

$created = [];
foreach ($sampleInvestments as $row) {
    $owner = $users[array_rand($users)];
    $id = generateUuid();
    $insertInv->execute([
        $id,
        $owner['id'],
        $row['title'],
        $row['sector'],
        $row['goal'],
        0.00, // amount_raised (will update if interests added)
        $row['equity'],
        null,
        null,
        $row['desc'],
        $row['end_date'],
        $row['status'],
        $row['visibility'],
    ]);
    $created[] = ['id' => $id, 'row' => $row, 'owner' => $owner];
}

echo "Inserted " . count($created) . " sample investments.\n";

if ($withInterests) {
    $insertInt = $db->prepare("INSERT INTO investment_interests (id, investment_id, investor_id, amount_pledged, message, status, created_at, updated_at) VALUES (?,?,?,?,?,'interested',NOW(),NOW())");
    $updateRaised = $db->prepare("UPDATE investments SET amount_raised = amount_raised + ? , status = CASE WHEN amount_raised + ? >= funding_goal THEN 'funded' ELSE status END WHERE id = ?");

    foreach ($created as $inv) {
        $numInterests = rand(2, 4);
        $pledgeTotal = 0.0;
        for ($i = 0; $i < $numInterests; $i++) {
            $investor = pickDifferentUser($users, $inv['owner']['id']);
            if (!$investor) break;
            $remaining = $inv['row']['goal'] - $pledgeTotal;
            if ($remaining <= 0) break;
            $portion = round($remaining * rand(15, 40) / 100, 2); // 15–40% of remaining
            if ($portion <= 0) $portion = 5.00;
            $pledgeTotal += $portion;
            $insertInt->execute([
                generateUuid(),
                $inv['id'],
                $investor['id'],
                $portion,
                'Excited to participate in this opportunity.'
            ]);
            $updateRaised->execute([$portion, $portion, $inv['id']]);
        }
    }
    echo "Added interests for seeded investments (randomized).\n";
}

echo "Seeding complete.\n";
