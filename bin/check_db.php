<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);

// Load environment
try {
    if (file_exists($root . '/.env')) {
        $dotenv = Dotenv::createImmutable($root);
        $dotenv->load();
    } elseif (file_exists($root . '/.env.example')) {
        $dotenv = Dotenv::createImmutable($root, '.env.example');
        $dotenv->load();
    }
} catch (Throwable $e) {
    echo "[error] Failed to load .env: " . $e->getMessage() . "\n";
    exit(1);
}

// Determine DB path
$dbPathEnv = $_ENV['DB_PATH'] ?? './data/app.db';
$dbPath = $dbPathEnv;
if (strpos($dbPath, './') === 0) {
    $dbPath = $root . '/' . ltrim($dbPath, './');
} elseif (strpos($dbPath, '/') === 0 || preg_match('#^[A-Za-z]:\\\\#', $dbPath)) {
    $dbPath = $dbPath;
} else {
    $dbPath = $root . '/' . $dbPath;
}

echo "=== Database Check ===\n";
echo "DB Path: $dbPath\n";
echo "DB Exists: " . (file_exists($dbPath) ? "YES" : "NO") . "\n";
echo "DB Size: " . (file_exists($dbPath) ? filesize($dbPath) . " bytes" : "N/A") . "\n";
echo "Data Directory Exists: " . (is_dir(dirname($dbPath)) ? "YES" : "NO") . "\n";
echo "Data Directory Writable: " . (is_writable(dirname($dbPath)) ? "YES" : "NO") . "\n";

if (file_exists($dbPath)) {
    try {
        $dsn = 'sqlite:' . $dbPath;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // List tables
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo "\n=== Tables in Database ===\n";
        if ($tables) {
            foreach ($tables as $t) {
                echo "  - $t\n";
            }
        } else {
            echo "  No tables found!\n";
        }

        // Check tickets table
        if (in_array('tickets', $tables)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM tickets");
            $count = $stmt->fetchColumn();
            echo "\n=== Tickets Table ===\n";
            echo "  Total tickets: $count\n";
        } else {
            echo "\n[error] 'tickets' table does NOT exist!\n";
            echo "[fix] Run migrations: php bin/migrate.php\n";
            exit(1);
        }

    } catch (Throwable $e) {
        echo "\n[error] Database query failed: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "\n[error] Database file does not exist!\n";
    echo "[fix] Run migrations: php bin/migrate.php\n";
    exit(1);
}

echo "\n[success] Database is properly set up.\n";
exit(0);
