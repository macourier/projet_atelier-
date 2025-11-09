<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);

// Load .env (same behavior as bootstrap.php)
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

// Compute DB path
$dbPathEnv = $_ENV['DB_PATH'] ?? './data/app.db';
$dbPath = $dbPathEnv;
if (strpos($dbPath, './') === 0) {
    $dbPath = $root . '/' . ltrim($dbPath, './');
} elseif (strpos($dbPath, '/') === 0 || preg_match('#^[A-Za-z]:\\\\#', $dbPath)) {
    $dbPath = $dbPath;
} else {
    $dbPath = $root . '/' . $dbPath;
}
echo "[info] Using SQLite DB at: $dbPath\n";

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if column already exists
    $cols = $pdo->query('PRAGMA table_info(clients)')->fetchAll(PDO::FETCH_ASSOC);
    $hasNote = false;
    foreach ($cols as $c) {
        if (strcasecmp($c['name'] ?? '', 'note') === 0) { $hasNote = true; break; }
    }
    if ($hasNote) {
        echo "[info] Column clients.note already exists. Nothing to do.\n";
    } else {
        echo "[action] ALTER TABLE clients ADD COLUMN note TEXT;\n";
        $pdo->exec('ALTER TABLE clients ADD COLUMN note TEXT');
        echo "[ok] Column clients.note added.\n";
    }

    // Show columns
    echo "[info] PRAGMA table_info(clients)\n";
    $cols2 = $pdo->query('PRAGMA table_info(clients)')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols2 as $c) {
        echo "  - {$c['cid']}: {$c['name']} {$c['type']}\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[error] " . $e->getMessage() . "\n");
    exit(2);
}
