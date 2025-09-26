<?php
declare(strict_types=1);

// includes/translator.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Get the currently selected language code (two-letter), fallback to 'en'
 */
function getCurrentLanguage(): string {
    $allowed = ['en','fr','ht','es']; // add other supported codes here
    $lang = $_SESSION['language'] ?? null;

    // Optionally auto-detect from DB for logged user or Accept-Language
    if (empty($lang) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browser = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (in_array($browser, $allowed, true)) $lang = $browser;
    }

    if (!is_string($lang) || !in_array($lang, $allowed, true)) {
        $lang = 'en';
    }
    return $lang;
}

/**
 * Set language into session and (optionally) DB for logged-in user
 */
function setCurrentLanguage(string $lang): void {
    $allowed = ['en','fr','ht','es'];
    if (!in_array($lang, $allowed, true)) return;
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['language'] = $lang;

    // Optional: persist to DB if user logged in
    if (!empty($_SESSION['user_id'])) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("UPDATE users SET language = ? WHERE id = ?");
            $stmt->execute([$lang, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            // log but do not break
            error_log("Failed to persist language: " . $e->getMessage());
        }
    }
}

/**
 * Load translation file (cached in static)
 * @return array<string,string>
 */
function loadTranslations(string $lang): array {
    static $cache = [];
    if (isset($cache[$lang])) return $cache[$lang];

    $base = __DIR__ . '/../lang/';
    $file = $base . $lang . '.php';
    if (!file_exists($file)) {
        // fallback to english if missing
        $file = $base . 'en.php';
    }

    // include returns array
    $translations = @include $file;
    if (!is_array($translations)) $translations = [];

    $cache[$lang] = $translations;
    return $cache[$lang];
}

/**
 * Translate function
 * Supports simple replacement: __t('welcome_user', ['name' => 'Jean'])
 */
function __t(string $key, array $vars = []): string {
    $lang = getCurrentLanguage();
    $translations = loadTranslations($lang);
    $text = $translations[$key] ?? $key;

    if (!empty($vars)) {
        foreach ($vars as $k => $v) {
            $text = str_replace("{" . $k . "}", (string)$v, $text);
        }
    }
    return $text;
}
