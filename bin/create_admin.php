<?php
declare(strict_types=1);

/**
 * Usage:
 * php bin/create_admin.php [email] [password]
 *
 * Defaults:
 *  email: admin@example.com
 *  password: admin
 *
 * This script inserts an admin user into data/app.db users table.
 */

$email = $argv[1] ?? 'admin@example.com';
$password = $argv[2] ?? 'admin';

$root = dirname(__DIR__);
$dbPath = $root . '/data/app.db';

if (!file_exists($dbPath)) {
    fwrite(STDERR, "[error] Database not found at: $dbPath\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if user exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo "[info] User with email {$email} already exists (id: {$row['id']}).\n";
        exit(0);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, roles, created_at) VALUES (:email, :hash, :roles, CURRENT_TIMESTAMP)');
    $stmt->execute([
        'email' => $email,
        'hash' => $hash,
        'roles' => 'ROLE_ADMIN'
    ]);

    $id = $pdo->lastInsertId();
    echo "[ok] Admin user created.\n";
    echo "  id: {$id}\n";
    echo "  email: {$email}\n";
    echo "  password: {$password}\n";
    echo "Please change the password after first login.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[error] " . $e->getMessage() . PHP_EOL);
    exit(2);
}
