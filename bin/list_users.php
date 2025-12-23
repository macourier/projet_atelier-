<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);

// Load .env
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->load();
} elseif (file_exists($root . '/.env.example')) {
    $dotenv = Dotenv::createImmutable($root, '.env.example');
    $dotenv->load();
}

// Get DB path
$dbPathEnv = $_ENV['DB_PATH'] ?? './data/app.db';
$dbPath = $dbPathEnv;
if (strpos($dbPath, './') === 0) {
    $dbPath = $root . '/' . ltrim($dbPath, './');
}

echo "=== Liste des utilisateurs ===\n\n";

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $users = $pdo->query('SELECT id, email FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "Aucun utilisateur trouvé.\n";
    } else {
        foreach ($users as $user) {
            echo "ID: {$user['id']} | Email: {$user['email']}\n";
        }
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[error] " . $e->getMessage() . "\n");
    exit(1);
}
