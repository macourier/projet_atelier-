<?php
declare(strict_types=1);

/**
 * Script de mise à jour de base de données - Ajoute les colonnes manquantes
 * 
 * Usage:
 * php bin/update_db.php
 * 
 * Ce script ajoute la colonne 'note' à la table 'clients' si elle n'existe pas.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);

// Load .env
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

// Determine DB path
$dbPathEnv = $_ENV['DB_PATH'] ?? './data/app.db';
$dbPath = $dbPathEnv;
if (strpos($dbPath, './') === 0) {
    $dbPath = $root . '/' . ltrim($dbPath, './');
} elseif (strpos($dbPath, '/') !== 0 && !preg_match('#^[A-Za-z]:\\\\#', $dbPath)) {
    $dbPath = $root . '/' . $dbPath;
}

echo "[info] Using SQLite DB at: $dbPath\n";

if (!file_exists($dbPath)) {
    fwrite(STDERR, "[error] Database not found at: $dbPath\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check and add missing 'note' column to clients table
    echo "[info] Checking clients table...\n";
    
    $stmt = $pdo->query("PRAGMA table_info(clients)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasNoteColumn = false;
    foreach ($columns as $col) {
        if (strcasecmp($col['name'] ?? '', 'note') === 0) {
            $hasNoteColumn = true;
            break;
        }
    }

    if ($hasNoteColumn) {
        echo "[ok] Column 'clients.note' already exists ✓\n";
    } else {
        echo "[warn] Column 'clients.note' is missing. Adding it...\n";
        $pdo->exec("ALTER TABLE clients ADD COLUMN note TEXT");
        echo "[ok] Column 'clients.note' added successfully ✓\n";
    }

    echo "[summary] Database update completed successfully.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[error] Database update failed: " . $e->getMessage() . "\n");
    exit(2);
}
