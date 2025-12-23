<?php
$pdo = new PDO('sqlite:./data/app.db');
try {
    $pdo->exec('ALTER TABLE tickets ADD COLUMN notes TEXT DEFAULT NULL;');
    echo 'Colonne notes ajoutée avec succès' . PHP_EOL;
} catch (Exception $e) {
    echo 'Erreur: ' . $e->getMessage() . PHP_EOL;
}
