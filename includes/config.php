<?php
/**
 * Application Configuration
 * 
 * Handles loading environment variables and database connection
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Process valid lines
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Strip quotes if present
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            
            // Replace environment variables in values
            $value = preg_replace_callback('/\${([A-Za-z0-9_]+)}/', function ($matches) {
                return getenv($matches[1]) ?: $_ENV[$matches[1]] ?? $_SERVER[$matches[1]] ?? $matches[0];
            }, $value);
            
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Include constants
require_once __DIR__ . '/constants.php';

// Error reporting based on environment
if (getenv('APP_ENV') === 'development' && getenv('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set default timezone
date_default_timezone_set('America/Port-au-Prince');

/**
 * Database connection
 * @return PDO Database connection
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), $options);
        } catch (PDOException $e) {
            // Log error and display friendly message
            error_log("Database Connection Error: " . $e->getMessage());
            die("Could not connect to the database. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Get configuration value
 * @param string $key Configuration key
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value
 */
function config($key, $default = null) {
    return getenv($key) ?: $default;
}

// Application constants
// These can be overridden by environment variables or config files
define('APP_NAME', config('APP_NAME', 'SOSOL'));
define('APP_URL', config('APP_URL', 'http://sosol.local'));
define('APP_VERSION', config('APP_VERSION', '1.0.0'));
define('APP_ENV', config('APP_ENV', 'production'));                             
define('APP_DEBUG', config('APP_DEBUG', 'false'));

// Other configurations can be added here as needed
