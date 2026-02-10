<?php
declare(strict_types=1);

namespace App\Service;

use PDO;
use DateTime;

class PlanningService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Récupère les items de planning pour une période donnée, groupés par jour
     */
    public function getWeekItems(DateTime $startDate, DateTime $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM planning_items
            WHERE scheduled_date >= :start AND scheduled_date <= :end
            ORDER BY scheduled_date ASC, created_at ASC
        ");
        $stmt->execute([
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d')
        ]);
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Grouper par jour
        $grouped = [];
        foreach ($items as $item) {
            $date = $item['scheduled_date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $item;
        }
        
        return $grouped;
    }

    /**
     * Calcule le nombre d'interventions par jour pour une période
     */
    public function getWeeklyStats(DateTime $startDate, DateTime $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                scheduled_date,
                COUNT(*) as count
            FROM planning_items
            WHERE scheduled_date >= :start AND scheduled_date <= :end
            GROUP BY scheduled_date
            ORDER BY scheduled_date ASC
        ");
        $stmt->execute([
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d')
        ]);
        
        $stats = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[$row['scheduled_date']] = [
                'count' => (int)$row['count']
            ];
        }
        
        return $stats;
    }

    /**
     * Crée un nouvel item de planning
     */
    public function createItem(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO planning_items (title, scheduled_date, status)
            VALUES (:title, :date, :status)
        ");
        $stmt->execute([
            'title' => trim($data['title']),
            'date' => $data['scheduled_date'],
            'status' => $data['status'] ?? 'planned'
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Met à jour un item de planning
     */
    public function updateItem(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];
        
        if (isset($data['title'])) {
            $fields[] = 'title = :title';
            $params['title'] = trim($data['title']);
        }
        if (isset($data['scheduled_date'])) {
            $fields[] = 'scheduled_date = :date';
            $params['date'] = $data['scheduled_date'];
        }
        if (isset($data['status'])) {
            $fields[] = 'status = :status';
            $params['status'] = $data['status'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        
        $sql = "UPDATE planning_items SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Supprime un item de planning
     */
    public function deleteItem(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM planning_items WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Récupère un item par son ID
     */
    public function getItem(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM planning_items WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    /**
     * Récupère un item de planning par ticket_id
     */
    public function getByTicketId(int $ticketId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM planning_items WHERE ticket_id = :ticket_id LIMIT 1");
        $stmt->execute(['ticket_id' => $ticketId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item ?: null;
    }

    /**
     * Crée un item de planning depuis un ticket
     */
    public function createFromTicket(int $ticketId, array $data): int
    {
        // Récupérer les infos du ticket
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.bike_model, c.name as client_name 
            FROM tickets t 
            JOIN clients c ON t.client_id = c.id 
            WHERE t.id = :id
        ");
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ticket) {
            throw new \Exception('Ticket introuvable');
        }
        
        // Créer le titre automatiquement
        $title = "Réparation - {$ticket['client_name']} — {$ticket['bike_model']}";
        
        // Insérer dans planning_items
        $stmt = $this->pdo->prepare("
            INSERT INTO planning_items (title, scheduled_date, recovery_date, ticket_id, status, notes)
            VALUES (:title, :date, :recovery_date, :ticket_id, :status, :notes)
        ");
        $stmt->execute([
            'title' => $title,
            'date' => $data['recovery_date'],
            'recovery_date' => $data['recovery_date'],
            'ticket_id' => $ticketId,
            'status' => 'planned',
            'notes' => $data['notes'] ?? null
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Supprime un item de planning par ticket_id
     */
    public function deleteByTicketId(int $ticketId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM planning_items WHERE ticket_id = :ticket_id");
        return $stmt->execute(['ticket_id' => $ticketId]);
    }

    /**
     * Calcule le lundi de la semaine pour une date donnée
     */
    public function getMondayOfWeek(DateTime $date): DateTime
    {
        $dayOfWeek = (int)$date->format('N'); // 1 = lundi, 7 = dimanche
        $monday = clone $date;
        $monday->modify('+' . (1 - $dayOfWeek) . ' days');
        return $monday;
    }

    /**
     * Compte toutes les interventions programmées à partir d'aujourd'hui
     */
    public function countFutureInterventions(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM planning_items
            WHERE scheduled_date >= CURRENT_DATE
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }
}
