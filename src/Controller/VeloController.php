<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class VeloController
{
    private array $container;
    private $pdo;
    private $twig;

    public function __construct(array $container = [])
    {
        $this->container = $container;
        $this->pdo = $container['pdo'] ?? null;
        $this->twig = $container['twig'] ?? null;
    }

    public function index(Request $request, Response $response): Response
    {
        $velos = [];
        if ($this->pdo) {
            $stmt = $this->pdo->query('SELECT v.*, c.name AS client_name FROM velos v LEFT JOIN clients c ON c.id = v.client_id ORDER BY v.created_at DESC');
            $velos = $stmt->fetchAll();
        }
        $response->getBody()->write($this->twig->render('velos/index.twig', ['velos' => $velos]));
        return $response;
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $velo = null;
        $clients = [];
        $selectedClientId = null;
        if ($this->pdo) {
            if ($id > 0) {
                $stmt = $this->pdo->prepare('SELECT * FROM velos WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $id]);
                $velo = $stmt->fetch();
            } else {
                // Pré-sélection client via query param ?client_id=...
                $query = $request->getQueryParams();
                if (!empty($query['client_id'])) {
                    $selectedClientId = (int)$query['client_id'];
                }
            }
            $stmt = $this->pdo->query('SELECT id, name FROM clients ORDER BY name');
            $clients = $stmt->fetchAll();
        }
        $response->getBody()->write($this->twig->render('velos/edit.twig', [
            'velo' => $velo,
            'clients' => $clients,
            'selectedClientId' => $selectedClientId
        ]));
        return $response;
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $data = (array)$request->getParsedBody();
        $client_id = (int)($data['client_id'] ?? 0);
        $brand = trim($data['brand'] ?? '');
        $model = trim($data['model'] ?? '');
        $serial = trim($data['serial'] ?? '');
        $notes = trim($data['notes'] ?? '');

        if (!$this->pdo) {
            $response->getBody()->write('Database not available');
            return $response->withStatus(500);
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare('UPDATE velos SET client_id = :client_id, brand = :brand, model = :model, serial = :serial, notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute([
                'client_id' => $client_id,
                'brand' => $brand,
                'model' => $model,
                'serial' => $serial,
                'notes' => $notes,
                'id' => $id
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO velos (client_id, brand, model, serial, notes) VALUES (:client_id, :brand, :model, :serial, :notes)');
            $stmt->execute([
                'client_id' => $client_id,
                'brand' => $brand,
                'model' => $model,
                'serial' => $serial,
                'notes' => $notes
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }

        return $response->withHeader('Location', '/velos')->withStatus(302);
    }
}
