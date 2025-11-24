<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Bootstrap app services and environment
$container = require __DIR__ . '/../src/bootstrap.php';

 // Create Slim app
$app = AppFactory::create();

 // Add routing middleware and error middleware
 $app->addRoutingMiddleware();
$displayErrorDetails = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);

// Include routes (expects $app and $container in scope)
(require __DIR__ . '/../src/routes.php')($app, $container);

// Run app
$app->run();
