<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class TicketController
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

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $ticket = null;
        $client = null;
        $prestations = [];
        $consommables = [];

        if ($this->pdo && $id > 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $ticket = $stmt->fetch();

            if ($ticket) {
                $stmt = $this->pdo->prepare('SELECT * FROM ticket_prestations WHERE ticket_id = :id');
                $stmt->execute(['id' => $id]);
                $prestations = $stmt->fetchAll();

                $stmt = $this->pdo->prepare('SELECT * FROM ticket_consommables WHERE ticket_id = :id');
                $stmt->execute(['id' => $id]);
                $consommables = $stmt->fetchAll();

                $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $ticket['client_id']]);
                $client = $stmt->fetch();
            }
        }

        $response->getBody()->write($this->twig->render('tickets/edit.twig', [
            'ticket' => $ticket,
            'client' => $client,
            'prestations' => $prestations,
            'consommables' => $consommables
        ]));
        return $response;
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $data = (array)$request->getParsedBody();
        $description = trim($data['description'] ?? '');
        // For simplicity we only update description and status here.
        if (!$this->pdo) {
            $response->getBody()->write('Database not available');
            return $response->withStatus(500);
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare('UPDATE tickets SET description = :description, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute(['description' => $description, 'id' => $id]);
        } else {
            // Create basic ticket requires a client_id
            $client_id = (int)($data['client_id'] ?? 0);
            $stmt = $this->pdo->prepare('INSERT INTO tickets (client_id, description) VALUES (:client_id, :description)');
            $stmt->execute(['client_id' => $client_id, 'description' => $description]);
            $id = (int)$this->pdo->lastInsertId();
        }

        return $response->withHeader('Location', '/tickets/' . $id . '/edit')->withStatus(302);
    }
}
