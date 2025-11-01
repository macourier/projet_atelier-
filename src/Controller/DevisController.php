<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class DevisController
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
        $devis = null;
        $client = null;

        if ($this->pdo && $id > 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM devis WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $devis = $stmt->fetch();

            if ($devis) {
                $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $devis['client_id']]);
                $client = $stmt->fetch();
            }
        }

        if (!$devis) {
            $response->getBody()->write($this->twig->render('devis/show.twig', ['devis' => null, 'error' => 'Devis non trouvé.']));
            return $response;
        }

        $response->getBody()->write($this->twig->render('devis/show.twig', [
            'devis' => $devis,
            'client' => $client
        ]));
        return $response;
    }
}
