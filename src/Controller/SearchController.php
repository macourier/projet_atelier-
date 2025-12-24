<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;

class SearchController
{
    private array $container;
    private ?PDO $pdo;
    private $twig;

    public function __construct(array $container = [])
    {
        $this->container = $container;
        $this->pdo = $container['pdo'] ?? null;
        $this->twig = $container['twig'] ?? null;
    }

    public function index(Request $request, Response $response): Response
    {
        $q = trim((string)($request->getQueryParams()['q'] ?? ''));
        $results = [
            'q' => $q,
            'clients' => [],
            'tickets' => [],
            'devis' => [],
            'factures' => [],
        ];

        if ($this->pdo && $q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

            // Clients: name, email, phone
            $stmt = $this->pdo->prepare("SELECT id, name, email, phone FROM clients WHERE name LIKE :q ESCAPE '\\' OR email LIKE :q ESCAPE '\\' OR phone LIKE :q ESCAPE '\\' ORDER BY name LIMIT 10");
            $stmt->execute(['q' => $like]);
            $results['clients'] = $stmt->fetchAll() ?: [];

            // Tickets: id (string match), description
            $stmt = $this->pdo->prepare("SELECT id, description, status, total_ht FROM tickets WHERE CAST(id AS VARCHAR) LIKE :q ESCAPE '\\' OR description LIKE :q ESCAPE '\\' ORDER BY created_at DESC LIMIT 10");
            $stmt->execute(['q' => $like]);
            $results['tickets'] = $stmt->fetchAll() ?: [];

            // Devis: numero, status
            $stmt = $this->pdo->prepare("SELECT id, numero, status, pdf_path FROM devis WHERE numero LIKE :q ESCAPE '\\' OR status LIKE :q ESCAPE '\\' ORDER BY created_at DESC LIMIT 10");
            $stmt->execute(['q' => $like]);
            $results['devis'] = $stmt->fetchAll() ?: [];

            // Factures: numero, status
            $stmt = $this->pdo->prepare("SELECT id, numero, status, pdf_path FROM factures WHERE numero LIKE :q ESCAPE '\\' OR status LIKE :q ESCAPE '\\' ORDER BY created_at DESC LIMIT 10");
            $stmt->execute(['q' => $like]);
            $results['factures'] = $stmt->fetchAll() ?: [];
        }

        $html = $this->twig->render('search/results.twig', $results);
        $response->getBody()->write($html);
        return $response;
    }
}
