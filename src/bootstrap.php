<?php
declare(strict_types=1);

use Dotenv\Dotenv;
use Twig\Loader\FilesystemLoader;
use Twig\Environment as TwigEnvironment;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Composer autoload if not already loaded
if (!file_exists(__DIR__ . '/../vendor/autoload.php') && !file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    // Nothing to do here; expecting composer install to be run by the developer.
} else {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Load .env
$envPath = dirname(__DIR__);
if (file_exists($envPath . '/.env')) {
    $dotenv = Dotenv::createImmutable($envPath);
    $dotenv->load();
} elseif (file_exists($envPath . '/.env.example')) {
    // Load example as fallback
    $dotenv = Dotenv::createImmutable($envPath, '.env.example');
    $dotenv->load();
}

// Ensure data directory exists
$dataDir = $envPath . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

// Create PDO
$driver = $_ENV['DB_DRIVER'] ?? 'sqlite';
$pdo = null;
try {
    if ($driver === 'sqlite') {
        $dbPath = $_ENV['DB_PATH'] ?? './data/app.db';
        $dsn = 'sqlite:' . $envPath . '/' . ltrim($dbPath, './');
        $pdo = new PDO($dsn);
    } else {
        // Support for other drivers (mysql, pgsql) if provided in future
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? null;
        $dbname = $_ENV['DB_NAME'] ?? null;
        $user = $_ENV['DB_USER'] ?? null;
        $pass = $_ENV['DB_PASSWORD'] ?? null;
        if ($driver === 'mysql') {
            $dsn = sprintf('mysql:host=%s;dbname=%s%s', $host, $dbname, $port ? ";port={$port}" : '');
        } elseif ($driver === 'pgsql') {
            $dsn = sprintf('pgsql:host=%s;dbname=%s%s', $host, $dbname, $port ? ";port={$port}" : '');
        } else {
            throw new RuntimeException('Unsupported DB_DRIVER: ' . $driver);
        }
        $pdo = new PDO($dsn, $user, $pass);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // In case of DB error, we still continue but $pdo may be null
    $pdo = null;
}

// Setup Twig
$templatesPath = $envPath . '/templates';
$twigLoader = new FilesystemLoader($templatesPath);
$twig = new TwigEnvironment($twigLoader, [
    'cache' => false,
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
]);


// Simple container array
$container = [];

// Basic services
$container['env'] = $_ENV;
$container['pdo'] = $pdo;
$container['twig'] = $twig;

// Lazy service factories for services that will be implemented
$container['services'] = [];

// PdfService factory
$container['services']['pdf'] = function () use (&$container) {
    if (!class_exists(\App\Service\PdfService::class)) {
        throw new RuntimeException('PdfService class not found. Did you run composer install and create src/Service/PdfService.php?');
    }
    return new \App\Service\PdfService($container['twig']);
};

// MailerService factory
$container['services']['mailer'] = function () use (&$container) {
    if (!class_exists(\App\Service\MailerService::class)) {
        throw new RuntimeException('MailerService class not found. Did you run composer install and create src/Service/MailerService.php?');
    }
    return new \App\Service\MailerService($_ENV['MAILER_DSN'] ?? '', $container['pdo'] ?? null);
};

// NumberingService factory
$container['services']['numbering'] = function () use (&$container) {
    if (!class_exists(\App\Service\NumberingService::class)) {
        throw new RuntimeException('NumberingService class not found. Did you run composer install and create src/Service/NumberingService.php?');
    }
    return new \App\Service\NumberingService($container['pdo'] ?? null);
};

// Helper to get service (instantiates lazy services)
$container['get'] = function (string $key) use (&$container) {
    if (isset($container['services'][$key]) && is_callable($container['services'][$key])) {
        // Replace factory with actual instance after creation
        $instance = $container['services'][$key]();
        $container['services'][$key] = $instance;
        return $instance;
    }
    return $container[$key] ?? null;
};

return $container;
