<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class ClientController
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
        $clients = [];
        if ($this->pdo) {
            $stmt = $this->pdo->query('SELECT * FROM clients ORDER BY name');
            $clients = $stmt->fetchAll();
        }
        $response->getBody()->write($this->twig->render('clients/index.twig', ['clients' => $clients]));
        return $response;
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $client = null;
        if ($this->pdo && $id > 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $client = $stmt->fetch();
        }
        if (!$client) {
            $response->getBody()->write($this->twig->render('clients/show.twig', ['client' => null, 'error' => 'Client non trouvé.']));
            return $response;
        }
        $response->getBody()->write($this->twig->render('clients/show.twig', ['client' => $client]));
        return $response;
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $client = null;
        if ($this->pdo && $id > 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $client = $stmt->fetch();
        }
        $response->getBody()->write($this->twig->render('clients/edit.twig', ['client' => $client]));
        return $response;
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $data = (array)$request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $address = trim($data['address'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if (!$this->pdo) {
            $response->getBody()->write('Database not available');
            return $response->withStatus(500);
        }

        if ($id > 0) {
            // update
            $stmt = $this->pdo->prepare('UPDATE clients SET name = :name, address = :address, email = :email, phone = :phone, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute([
                'name' => $name,
                'address' => $address,
                'email' => $email,
                'phone' => $phone,
                'id' => $id
            ]);
        } else {
            // create
            $stmt = $this->pdo->prepare('INSERT INTO clients (name, address, email, phone) VALUES (:name, :address, :email, :phone)');
            $stmt->execute([
                'name' => $name,
                'address' => $address,
                'email' => $email,
                'phone' => $phone
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }

        return $response->withHeader('Location', '/clients/'.$id)->withStatus(302);
    }
}
