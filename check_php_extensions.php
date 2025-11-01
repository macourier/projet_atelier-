<?php
// check_php_extensions.php
// Simple script to check presence of PHP extensions required by the project.
// Usage: php check_php_extensions.php

$extensions = ['gd', 'mbstring', 'zip'];

foreach ($extensions as $ext) {
    echo $ext . ': ' . (extension_loaded($ext) ? "✅ activé" : "❌ manquant") . PHP_EOL;
}
