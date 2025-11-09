<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);

// Load .env or .env.example (same behavior as bootstrap.php)
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

// Compute DB path same as src/bootstrap.php
$driver = $_ENV['DB_DRIVER'] ?? 'sqlite';
if ($driver !== 'sqlite') {
    fwrite(STDERR, "[error] Only sqlite supported in this inspector.\n");
    exit(1);
}

$dbPathEnv = $_ENV['DB_PATH'] ?? './data/app.db';
$dbPath = $dbPathEnv;
if (strpos($dbPath, './') === 0) {
    $dbPath = $root . '/' . ltrim($dbPath, './');
} elseif (strpos($dbPath, '/') === 0 || preg_match('#^[A-Za-z]:\\\\#', $dbPath)) {
    // absolute path provided -> keep as is
    $dbPath = $dbPath;
} else {
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

    echo "[info] PRAGMA table_info(tickets)\n";
    $cols = $pdo->query('PRAGMA table_info(tickets)')->fetchAll(PDO::FETCH_ASSOC);
    if ($cols) {
        foreach ($cols as $c) {
            echo "  - {$c['cid']}: {$c['name']} {$c['type']}\n";
        }
    } else {
        echo "  (no columns or table not found)\n";
    }

    echo "[info] PRAGMA table_info(tickets_new)\n";
    try {
        $cols2 = $pdo->query('PRAGMA table_info(tickets_new)')->fetchAll(PDO::FETCH_ASSOC);
        if ($cols2) {
            foreach ($cols2 as $c) {
                echo "  - {$c['cid']}: {$c['name']} {$c['type']}\n";
            }
        } else {
            echo "  (table tickets_new not found)\n";
        }
    } catch (Throwable $e) {
        echo "  (error reading tickets_new: " . $e->getMessage() . ")\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[error] " . $e->getMessage() . "\n");
    exit(2);
}
