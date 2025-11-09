<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);

// Load .env or .env.example
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->load();
    echo "[info] Loaded .env\n";
} elseif (file_exists($root . '/.env.example')) {
    $dotenv = Dotenv::createImmutable($root, '.env.example');
    $dotenv->load();
    echo "[info] .env absent — loaded .env.example as fallback\n";
} else {
    echo "[warn] No .env or .env.example found. Using defaults.\n";
}

// Resolve DB path like src/bootstrap.php
$dbPathEnv = $_ENV['DB_PATH'] ?? './data/app.db';
$dbPath = $dbPathEnv;
if (strpos($dbPath, './') === 0) {
    $dbPath = $root . '/' . ltrim($dbPath, './');
} elseif (!(strpos($dbPath, '/') === 0 || preg_match('#^[A-Za-z]:\\\\#', $dbPath))) {
    $dbPath = $root . '/' . $dbPath;
}

echo "[info] Using SQLite DB at: $dbPath\n";

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "[info] PRAGMA database_list\n";
    $dbs = $pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dbs as $d) {
        echo "  - {$d['name']} -> {$d['file']}\n";
    }

    echo "[info] PRAGMA table_info(prestations_catalogue)\n";
    $cols = $pdo->query('PRAGMA table_info(prestations_catalogue)')->fetchAll(PDO::FETCH_ASSOC);
    if ($cols) {
        foreach ($cols as $c) {
            echo "  - {$c['cid']}: {$c['name']} {$c['type']}\n";
        }
    } else {
        echo "  (no columns or table not found)\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[error] " . $e->getMessage() . "\n");
    exit(2);
}
