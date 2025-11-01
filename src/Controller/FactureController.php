<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class FactureController
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

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $facture = null;
        $client = null;

        if ($this->pdo && $id > 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM factures WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $facture = $stmt->fetch();

            if ($facture) {
                $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $facture['client_id']]);
                $client = $stmt->fetch();
            }
        }

        if (!$facture) {
            $response->getBody()->write($this->twig->render('factures/show.twig', ['facture' => null, 'error' => 'Facture non trouvée.']));
            return $response;
        }

        $response->getBody()->write($this->twig->render('factures/show.twig', [
            'facture' => $facture,
            'client' => $client
        ]));
        return $response;
    }
}
