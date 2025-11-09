<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);

// Load environment like app
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->load();
    echo "[info] Loaded .env\n";
} elseif (file_exists($root . '/.env.example')) {
    $dotenv = Dotenv::createImmutable($root, '.env.example');
    $dotenv->load();
    echo "[info] .env example loaded\n";
} else {
    echo "[warn] No .env found\n";
}

// Resolve DB path (same logic as bootstrap)
$dbPathEnv = $_ENV['DB_PATH'] ?? './data/app.db';
$dbPath = $dbPathEnv;
if (strpos($dbPath, './') === 0) {
    $dbPath = $root . '/' . ltrim($dbPath, './');
} elseif (!(strpos($dbPath, '/') === 0 || preg_match('#^[A-Za-z]:\\\\#', $dbPath))) {
    $dbPath = $root . '/' . $dbPath;
}

echo "[info] Using DB: $dbPath\n";

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cols = $pdo->query('PRAGMA table_info(prestations_catalogue)')->fetchAll(PDO::FETCH_ASSOC);
    $hasPieceLib = false;
    $hasPiecePrix = false;
    foreach ($cols as $c) {
        $name = strtolower($c['name'] ?? '');
        if ($name === 'piece_libelle') $hasPieceLib = true;
        if ($name === 'piece_prix_ht') $hasPiecePrix = true;
    }

    $pdo->exec('PRAGMA foreign_keys = OFF');
    $added = false;

    if (!$hasPieceLib) {
        echo "[patch] Adding column piece_libelle TEXT\n";
        // Pas de DEFAULT non-constant pour éviter soucis, on backfill juste après
        $pdo->exec("ALTER TABLE prestations_catalogue ADD COLUMN piece_libelle TEXT");
        $added = true;
    } else {
        echo "[skip] piece_libelle already present\n";
    }

    if (!$hasPiecePrix) {
        echo "[patch] Adding column piece_prix_ht REAL\n";
        $pdo->exec("ALTER TABLE prestations_catalogue ADD COLUMN piece_prix_ht REAL");
        $added = true;
    } else {
        echo "[skip] piece_prix_ht already present\n";
    }

    if ($added || (!$hasPieceLib || !$hasPiecePrix)) {
        echo "[patch] Backfilling defaults for NULL values\n";
        $pdo->exec("UPDATE prestations_catalogue SET piece_libelle = COALESCE(piece_libelle, 'Pièce')");
        $pdo->exec("UPDATE prestations_catalogue SET piece_prix_ht = COALESCE(piece_prix_ht, 0)");
    }

    $pdo->exec('PRAGMA foreign_keys = ON');

    // Show final columns
    echo "[info] Final columns:\n";
    $cols = $pdo->query('PRAGMA table_info(prestations_catalogue)')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  - {$c['cid']}: {$c['name']} {$c['type']}\n";
    }

    echo "[ok] Patch complete\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[error] " . $e->getMessage() . "\n");
    exit(2);
}
