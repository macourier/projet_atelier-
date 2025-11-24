<?php
declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

return function (App $app, array $container) {
    // Health / dashboard
    $app->get('/', function (Request $request, Response $response) use ($container) {
        // Redirect to new home: /catalogue
        return $response
            ->withHeader('Location', '/catalogue')
            ->withStatus(302);
    });

    // Backward compat: /dashboard -> redirect to /catalogue
    $app->get('/dashboard', function (Request $request, Response $response) {
        return $response->withHeader('Location', '/catalogue')->withStatus(302);
    });

    // Auth (use closures to inject the local $container)
    $app->get('/login', function (Request $request, Response $response) use ($container) {
        $ctrl = new \App\Controller\AuthController($container);
        return $ctrl->showLogin($request, $response);
    });
    $app->post('/login', function (Request $request, Response $response) use ($container) {
        $ctrl = new \App\Controller\AuthController($container);
        return $ctrl->handleLogin($request, $response);
    });
    $app->get('/logout', function (Request $request, Response $response) use ($container) {
        $ctrl = new \App\Controller\AuthController($container);
        return $ctrl->logout($request, $response);
    });

    // Protected routes group (closures instantiate controllers with $container)
    $authMiddleware = new \App\Middleware\AuthMiddleware($container);

    $app->group('', function (\Slim\Routing\RouteCollectorProxy $group) use ($container) {
        // New home: Catalogue (ex /devis/new)
        $group->get('/catalogue', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\DevisController($container);
            if (method_exists($ctrl, 'builder')) {
                return $ctrl->builder($request, $response);
            }
            $response->getBody()->write('Not implemented');
            return $response->withStatus(501);
        });
        // Clients
        $group->get('/clients', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\ClientController($container);
            return $ctrl->index($request, $response);
        });
        $group->get('/clients/{id}', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\ClientController($container);
            return $ctrl->show($request, $response, $args);
        });
        $group->get('/clients/{id}/edit', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\ClientController($container);
            return $ctrl->edit($request, $response, $args);
        });
        $group->post('/clients/{id}/edit', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\ClientController($container);
            return $ctrl->update($request, $response, $args);
        });
        // Sélectionner un client existant depuis le catalogue: créer ticket + lignes et rediriger vers dashboard
        $group->post('/clients/{id}/select', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\ClientController($container);
            if (method_exists($ctrl, 'select')) {
                return $ctrl->select($request, $response, $args);
            }
            $response->getBody()->write('Not implemented');
            return $response->withStatus(501);
        });


        // Tickets
        // File d'attente (tickets ouverts, ordre: plus ancien -> plus récent)
        $group->get('/tickets/queue', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\TicketController($container);
            if (method_exists($ctrl, 'queue')) {
                return $ctrl->queue($request, $response);
            }
            $response->getBody()->write('Not implemented');
            return $response->withStatus(501);
        });
        $group->get('/tickets/{id}/edit', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\TicketController($container);
            return $ctrl->edit($request, $response, $args);
        });
        $group->post('/tickets/{id}/edit', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\TicketController($container);
            return $ctrl->update($request, $response, $args);
        });
        // Tickets → Devis / Facturation
        $group->get('/tickets/{id}/devis/preview', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\TicketController($container);
            if (method_exists($ctrl, 'devisPreview')) {
                return $ctrl->devisPreview($request, $response, $args);
            }
            $response->getBody()->write('Not implemented');
            return $response->withStatus(501);
        });
        $group->get('/tickets/{id}/devis/pdf', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\TicketController($container);
            if (method_exists($ctrl, 'devisPdf')) {
                return $ctrl->devisPdf($request, $response, $args);
            }
            $response->getBody()->write('Not implemented');
            return $response->withStatus(501);
        });
        $group->post('/tickets/{id}/facturer/confirm', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\TicketController($container);
            if (method_exists($ctrl, 'facturerConfirm')) {
                return $ctrl->facturerConfirm($request, $response, $args);
            }
            $response->getBody()->write('Not implemented');
            return $response->withStatus(501);
        });

        // Devis
        $group->get('/devis/new', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\DevisController($container);
            if (method_exists($ctrl, 'builder')) {
                return $ctrl->builder($request, $response);
            }
            $response->getBody()->write('Not implemented');
            return $response->withStatus(501);
        });
        $group->get('/devis/{id}/show', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\DevisController($container);
            return $ctrl->show($request, $response, $args);
        });

        // Devis PDF direct (sans persistance de ticket/clients) — ouvre dans un nouvel onglet côté client
        $group->post('/devis/pdf/direct', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\DevisController($container);
            if (method_exists($ctrl, 'directPdf')) {
                return $ctrl->directPdf($request, $response);
            }
            $response->getBody()->write('Not implemented');
            return $response->withStatus(501);
        });

        // Factures
        $group->get('/factures/{id}/show', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\FactureController($container);
            return $ctrl->show($request, $response, $args);
        });

        // Admin Dashboard
        $group->get('/admin', function (Request $request, Response $response) use ($container) {
            $html = $container['twig']->render('admin/dashboard.twig', [
                'env' => $container['env'] ?? []
            ]);
            $response->getBody()->write($html);
            return $response;
        });

        // Admin Prestations (catalogue)
        $group->get('/admin/prestations', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\AdminPrestationsController($container);
            return $ctrl->index($request, $response);
        });
        $group->post('/admin/prestations', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\AdminPrestationsController($container);
            return $ctrl->create($request, $response);
        });

        // Ajouter catégorie (tuile simple) - créera une vraie entité categories
        $group->post('/admin/categories', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\AdminPrestationsController($container);
            return $ctrl->createCategory($request, $response);
        });
        // Supprimer une catégorie (et ses prestations)
        $group->post('/admin/categories/{id}/delete', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\AdminPrestationsController($container);
            return $ctrl->deleteCategory($request, $response, $args);
        });
        // placer la route statique avant la route paramétrée pour éviter le shadowing
        $group->post('/admin/prestations/undo', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\AdminPrestationsController($container);
            return $ctrl->undo($request, $response);
        });
        $group->post('/admin/prestations/{id}', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\AdminPrestationsController($container);
            return $ctrl->update($request, $response, $args);
        });
        $group->post('/admin/prestations/{id}/delete', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\AdminPrestationsController($container);
            return $ctrl->delete($request, $response, $args);
        });
        $group->get('/admin/prestations/export.xlsx', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\AdminPrestationsController($container);
            return $ctrl->export($request, $response);
        });

        // Admin Pièces (consommables)
        $group->get('/admin/pieces', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\AdminPiecesController($container);
            return $ctrl->index($request, $response);
        });
        $group->post('/admin/pieces', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\AdminPiecesController($container);
            return $ctrl->create($request, $response);
        });
        // placer la route statique avant la route paramétrée pour éviter le shadowing
        $group->post('/admin/pieces/undo', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\AdminPiecesController($container);
            return $ctrl->undo($request, $response);
        });
        $group->post('/admin/pieces/{id}', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\AdminPiecesController($container);
            return $ctrl->update($request, $response, $args);
        });
        $group->post('/admin/pieces/{id}/delete', function (Request $request, Response $response, $args) use ($container) {
            $ctrl = new \App\Controller\AdminPiecesController($container);
            return $ctrl->delete($request, $response, $args);
        });
        $group->get('/admin/pieces/export.xlsx', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\AdminPiecesController($container);
            return $ctrl->export($request, $response);
        });
        // Search
        $group->get('/search', function (Request $request, Response $response) use ($container) {
            $ctrl = new \App\Controller\SearchController($container);
            return $ctrl->index($request, $response);
        });
    })->add($authMiddleware);
};
