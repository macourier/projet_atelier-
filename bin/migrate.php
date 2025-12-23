<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);

// Load .env or .env.example
try {
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
} catch (Throwable $e) {
    fwrite(STDERR, "[error] Failed to load .env file: " . $e->getMessage() . "\n");
    exit(1);
}

// Ensure data directory exists
$dataDir = $root . '/data';
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0755, true)) {
        fwrite(STDERR, "[error] Failed to create data directory: $dataDir\n");
        exit(1);
    }
    echo "[info] Created data directory: $dataDir\n";
}

// Determine DB path
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

$needsInit = !file_exists($dbPath);

try {
    $dsn = 'sqlite:' . $dbPath;
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read and execute all SQL migrations sorted (e.g., 001_init.sql, 002_*.sql, ...)
    $migrationsDir = $root . '/migrations';
    if (!is_dir($migrationsDir)) {
        fwrite(STDERR, "[error] Migrations directory not found: $migrationsDir\n");
        exit(1);
    }

    $all = scandir($migrationsDir);
    $files = [];
    foreach ($all as $f) {
        if (preg_match('/^\d+_.*\.sql$/', $f)) {
            $files[] = $f;
        }
    }
    natsort($files);
    $files = array_values($files);

    if (empty($files)) {
        fwrite(STDERR, "[warn] No migration *.sql files found in $migrationsDir\n");
    } else {
        foreach ($files as $f) {
            $path = $migrationsDir . '/' . $f;
            $sql = file_get_contents($path);
            if ($sql === false) {
                fwrite(STDERR, "[error] Failed to read migration file: $path\n");
                exit(1);
            }

            // Idempotency guards for SQLite (skip if already applied)
            if ($f === '003_add_ticket_id_to_factures.sql') {
                $stmt = $pdo->query("PRAGMA table_info(factures)");
                $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $has = false;
                foreach ($cols as $c) {
                    if (strcasecmp($c['name'] ?? '', 'ticket_id') === 0) { $has = true; break; }
                }
                if ($has) {
                    echo "[skip] Column factures.ticket_id already exists — skipping $f\n";
                    continue;
                }
            }
            if ($f === '004_tickets_bike_fields.sql') {
                $stmt = $pdo->query("PRAGMA table_info(tickets)");
                $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $hasBike = false;
                foreach ($cols as $c) {
                    if (strcasecmp($c['name'] ?? '', 'bike_brand') === 0) { $hasBike = true; break; }
                }
                if ($hasBike) {
                    echo "[skip] Columns tickets.bike_* already exist — skipping $f\n";
                    continue;
                }
            }

            // Execute SQL (SQLite supports multiple statements via exec)
            try {
                $pdo->exec($sql);
                echo "[ok] Applied migration: $f\n";
            } catch (Throwable $e) {
                // Continue on error to allow partially idempotent migrations
                fwrite(STDERR, "[warn] Migration $f failed but continuing: " . $e->getMessage() . "\n");
            }
        }
    }

    // List created tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($tables) {
        echo "[info] Tables in database:\n";
        foreach ($tables as $t) {
            echo "  - $t\n";
        }
    } else {
        echo "[warn] No tables found after migration.\n";
    }

    // Provide a short summary
    if ($needsInit) {
        echo "[summary] Database file created and initialized at: $dbPath\n";
    } else {
        echo "[summary] Migration applied to existing DB: $dbPath\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[error] Migration failed: " . $e->getMessage() . "\n");
    exit(2);
}
