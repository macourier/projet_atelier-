<?php
declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

return function (App $app, array $container) {
    // Health / dashboard
    $app->get('/', function (Request $request, Response $response) use ($container) {
        // Simple redirect to dashboard
        return $response
            ->withHeader('Location', '/dashboard')
            ->withStatus(302);
    });

    $app->get('/dashboard', function (Request $request, Response $response) use ($container) {
        $twig = $container['twig'];
        $body = $twig->render('dashboard.twig', []);
        $response->getBody()->write($body);
        return $response;
    });

    // Auth
    $app->get('/login', \App\Controller\AuthController::class . ':showLogin');
    $app->post('/login', \App\Controller\AuthController::class . ':handleLogin');
    $app->get('/logout', \App\Controller\AuthController::class . ':logout');

    // Protected routes group
    $authMiddleware = new \App\Middleware\AuthMiddleware($container);

    $app->group('', function (App $group) {
        // Clients
        $group->get('/clients', \App\Controller\ClientController::class . ':index');
        $group->get('/clients/{id}', \App\Controller\ClientController::class . ':show');
        $group->get('/clients/{id}/edit', \App\Controller\ClientController::class . ':edit');
        $group->post('/clients/{id}/edit', \App\Controller\ClientController::class . ':update');

        // Velos
        $group->get('/velos', \App\Controller\VeloController::class . ':index');
        $group->get('/velos/{id}/edit', \App\Controller\VeloController::class . ':edit');
        $group->post('/velos/{id}/edit', \App\Controller\VeloController::class . ':update');

        // Tickets
        $group->get('/tickets/{id}/edit', \App\Controller\TicketController::class . ':edit');
        $group->post('/tickets/{id}/edit', \App\Controller\TicketController::class . ':update');

        // Devis
        $group->get('/devis/{id}/show', \App\Controller\DevisController::class . ':show');

        // Factures
        $group->get('/factures/{id}/show', \App\Controller\FactureController::class . ':show');
    })->add($authMiddleware);
};
