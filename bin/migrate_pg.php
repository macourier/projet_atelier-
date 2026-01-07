#!/usr/bin/env php
<?php
/**
 * PostgreSQL Migration Script
 * Run this script to apply PostgreSQL migrations
 * 
 * Usage: php bin/migrate_pg.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env
$envPath = dirname(__DIR__);
if (file_exists($envPath . '/.env')) {
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();
} elseif (file_exists($envPath . '/.env.example')) {
    $dotenv = Dotenv::createImmutable($envPath, '.env.example');
    $dotenv->load();
}

// Check driver
$driver = $_ENV['DB_DRIVER'] ?? 'sqlite';
if ($driver !== 'pgsql') {
    echo "⚠️  Warning: DB_DRIVER is set to '$driver' but this script is for PostgreSQL.\n";
    echo "   Please set DB_DRIVER=pgsql in your .env file.\n";
    echo "   Current migrations will run anyway...\n\n";
}

// Get PostgreSQL connection parameters
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '5432';
$dbname = $_ENV['DB_NAME'] ?? 'projet_atelier';
$user = $_ENV['DB_USER'] ?? 'postgres';
$pass = $_ENV['DB_PASSWORD'] ?? '';

echo "📦 PostgreSQL Migration Script\n";
echo "============================\n";
echo "Host: $host:$port\n";
echo "Database: $dbname\n";
echo "User: $user\n\n";

// Create PDO connection
try {
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname);
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "✅ Connected to PostgreSQL database\n\n";
} catch (PDOException $e) {
    echo "❌ Failed to connect to PostgreSQL:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "💡 Make sure PostgreSQL is running and the database exists.\n";
    echo "   You can create the database with:\n";
    echo "   createdb -U $user $dbname\n";
    exit(1);
}

// Get migrations directory
$migrationsDir = $envPath . '/migrations';
if (!is_dir($migrationsDir)) {
    echo "❌ Migrations directory not found: $migrationsDir\n";
    exit(1);
}

// Get PostgreSQL migration files
$files = [];
foreach (scandir($migrationsDir) as $f) {
    if (preg_match('/^\d+_.*_pg\.sql$/', $f)) {
        $files[] = $f;
    }
}
natsort($files);
$files = array_values($files);

if (empty($files)) {
    echo "❌ No PostgreSQL migration files found in $migrationsDir\n";
    exit(1);
}

echo "Found " . count($files) . " PostgreSQL migration(s):\n\n";

// Create migrations tracking table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version TEXT PRIMARY KEY,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    echo "❌ Failed to create schema_migrations table: " . $e->getMessage() . "\n";
    exit(1);
}

// Get already applied migrations
$stmt = $pdo->query("SELECT version FROM schema_migrations ORDER BY version");
$appliedMigrations = [];
while ($row = $stmt->fetch()) {
    $appliedMigrations[$row['version']] = true;
}

// Run migrations
$migrationCount = 0;
$errorCount = 0;

foreach ($files as $f) {
    $version = preg_replace('/_\d+_pg\.sql$/', '', $f);
    $version = str_replace('_pg.sql', '', $f);
    
    if (isset($appliedMigrations[$f])) {
        echo "   ⊘ $f (already applied)\n";
        continue;
    }
    
    $path = $migrationsDir . '/' . $f;
    $sql = file_get_contents($path);
    
    if ($sql === false) {
        echo "   ❌ $f (failed to read)\n";
        $errorCount++;
        continue;
    }
    
    echo "   ⬆️  $f";
    
    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $pdo->exec("INSERT INTO schema_migrations (version) VALUES ('$f')");
        $pdo->commit();
        echo " ✅\n";
        $migrationCount++;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo " ❌\n";
        echo "      Error: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n";
echo "============================\n";
if ($errorCount > 0) {
    echo "❌ Migration completed with $errorCount error(s)\n";
    exit(1);
} elseif ($migrationCount > 0) {
    echo "✅ Successfully applied $migrationCount migration(s)\n";
} else {
    echo "✅ All migrations are up to date\n";
}
echo "============================\n";