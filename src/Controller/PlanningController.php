<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use DateTime;
use App\Service\PlanningService;

class PlanningController
{
    private array $container;
    private $pdo;
    private $twig;
    private ?PlanningService $planningService = null;

    public function __construct(array $container = [])
    {
        $this->container = $container;
        $this->pdo = $container['pdo'] ?? null;
        $this->twig = $container['twig'] ?? null;
        
        if ($this->pdo) {
            $this->planningService = new PlanningService($this->pdo);
        }
    }

    /**
     * Affiche le planning de la semaine (courante ou ciblée)
     */
    public function index(Request $request, Response $response): Response
    {
        if (!$this->planningService || !$this->twig) {
            return $response->withStatus(500);
        }

        // Récupérer les paramètres de la semaine
        $queryParams = $request->getQueryParams();
        $weekParam = $queryParams['week'] ?? null;
        $yearParam = $queryParams['year'] ?? null;

        // Déterminer la semaine à afficher
        if ($weekParam && $yearParam) {
            // Semaine spécifique - créer le lundi de cette semaine
            $week = (int)$weekParam;
            $year = (int)$yearParam;
            $date = new DateTime();
            $date->setISODate($year, $week, 1); // 1 = lundi
            if ($date === false) {
                $date = new DateTime();
            }
        } else {
            // Semaine courante
            $date = new DateTime();
        }

        // Calculer lundi et dimanche de la semaine
        $weekStart = $this->planningService->getMondayOfWeek($date);
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');

        // Récupérer les items et les stats
        $weekItems = $this->planningService->getWeekItems($weekStart, $weekEnd);
        $weekStats = $this->planningService->getWeeklyStats($weekStart, $weekEnd);

        // Préparer les données pour chaque jour de la semaine
        $days = [];
        $daysOfWeek = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        
        for ($i = 0; $i < 7; $i++) {
            $dayDate = clone $weekStart;
            $dayDate->modify('+' . $i . ' days');
            $dateStr = $dayDate->format('Y-m-d');
            
            $days[] = [
                'name' => $daysOfWeek[$i],
                'date' => $dayDate,
                'date_str' => $dateStr,
                'items' => $weekItems[$dateStr] ?? [],
                'count' => $weekStats[$dateStr]['count'] ?? 0,
                'is_today' => $dateStr === (new DateTime())->format('Y-m-d')
            ];
        }

        // Calculer les URLs de navigation
        $prevWeekStart = clone $weekStart;
        $prevWeekStart->modify('-1 week');
        $nextWeekStart = clone $weekStart;
        $nextWeekStart->modify('+1 week');

        $prevUrl = '/planning?year=' . $prevWeekStart->format('Y') . '&week=' . $prevWeekStart->format('W');
        $nextUrl = '/planning?year=' . $nextWeekStart->format('Y') . '&week=' . $nextWeekStart->format('W');

        // Libellé de la semaine
        $weekLabel = 'Semaine du ' . $weekStart->format('d/m') . ' au ' . $weekEnd->format('d/m');

        // Vérifier si la semaine est vide
        $isEmpty = empty($weekItems);

        $html = $this->twig->render('planning/index.twig', [
            'days' => $days,
            'weekLabel' => $weekLabel,
            'prevUrl' => $prevUrl,
            'nextUrl' => $nextUrl,
            'isEmpty' => $isEmpty
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Crée un nouvel item de planning
     */
    public function create(Request $request, Response $response): Response
    {
        if (!$this->planningService || !$this->pdo) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Service non disponible']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        try {
            $data = (array)$request->getParsedBody();
            
            // Validation
            $title = trim($data['title'] ?? '');
            $scheduledDate = $data['scheduled_date'] ?? '';
            $status = trim($data['status'] ?? 'planned');

            if (empty($title)) {
                throw new \Exception('Le titre est obligatoire.');
            }
            if (empty($scheduledDate)) {
                throw new \Exception('La date est obligatoire.');
            }

            // Créer l'item
            $this->planningService->createItem([
                'title' => $title,
                'scheduled_date' => $scheduledDate,
                'status' => $status
            ]);

            // Rediriger vers le planning
            return $response->withHeader('Location', '/planning?success=1')->withStatus(302);

        } catch (\Exception $e) {
            // En cas d'erreur, rediriger avec message d'erreur
            return $response->withHeader('Location', '/planning?error=' . urlencode($e->getMessage()))->withStatus(302);
        }
    }

    /**
     * Met à jour un item de planning
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        if (!$this->planningService) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Service non disponible']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        try {
            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                throw new \Exception('ID invalide.');
            }

            $data = (array)$request->getParsedBody();
            
            // Validation
            $title = trim($data['title'] ?? '');
            $scheduledDate = $data['scheduled_date'] ?? '';
            $status = trim($data['status'] ?? 'planned');

            if (empty($title)) {
                throw new \Exception('Le titre est obligatoire.');
            }
            if (empty($scheduledDate)) {
                throw new \Exception('La date est obligatoire.');
            }

            // Mettre à jour l'item
            $this->planningService->updateItem($id, [
                'title' => $title,
                'scheduled_date' => $scheduledDate,
                'status' => $status
            ]);

            // Rediriger vers le planning
            return $response->withHeader('Location', '/planning?success=1')->withStatus(302);

        } catch (\Exception $e) {
            // En cas d'erreur, rediriger avec message d'erreur
            return $response->withHeader('Location', '/planning?error=' . urlencode($e->getMessage()))->withStatus(302);
        }
    }

    /**
     * Supprime un item de planning
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->planningService) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Service non disponible']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        try {
            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                throw new \Exception('ID invalide.');
            }

            // Supprimer l'item
            $this->planningService->deleteItem($id);

            // Rediriger vers le planning
            return $response->withHeader('Location', '/planning?deleted=1')->withStatus(302);

        } catch (\Exception $e) {
            // En cas d'erreur, rediriger avec message d'erreur
            return $response->withHeader('Location', '/planning?error=' . urlencode($e->getMessage()))->withStatus(302);
        }
    }
}
