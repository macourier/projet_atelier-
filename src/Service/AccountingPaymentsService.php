<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

class AccountingPaymentsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère la liste des paiements encaissés sur une période mensuelle
     * avec filtre optionnel par moyen de paiement
     *
     * @param int $year Année (ex: 2025)
     * @param int $month Mois (1-12)
     * @param string|null $method Filtre par moyen de paiement (CB|ESPECE)
     * @return array Liste des paiements
     */
    public function getMonthlyPayments(int $year, int $month, ?string $method = null): array
    {
        // Calcul de la fenêtre temporelle
        $startDate = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        
        // Fin = premier jour du mois suivant
        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        $endDate = sprintf('%04d-%02d-01 00:00:00', $nextYear, $nextMonth);

        // Construction de la requête
        $sql = "SELECT 
                    r.paid_at,
                    r.amount,
                    r.method,
                    c.name as client_name,
                    f.numero as invoice_ref,
                    f.client_id
                 FROM reglements r
                 LEFT JOIN factures f ON r.facture_id = f.id
                 LEFT JOIN clients c ON f.client_id = c.id
                 WHERE r.paid_at >= :start AND r.paid_at < :end";
        
        // Ajout du filtre par méthode si spécifié
        if ($method !== null && $method !== '') {
            $sql .= " AND r.method = :method";
        }
        
        $sql .= " ORDER BY r.paid_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':end', $endDate, PDO::PARAM_STR);
        
        if ($method !== null && $method !== '') {
            $stmt->bindValue(':method', $method, PDO::PARAM_STR);
        }
        
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater les données
        $payments = [];
        foreach ($rows as $row) {
            // Formatage de la date
            $date = new \DateTime($row['paid_at']);
            
            $payments[] = [
                'paid_at' => $row['paid_at'],
                'date' => $date->format('d/m/Y H:i'),
                'amount' => (float)$row['amount'],
                'method' => $row['method'] ?? '—',
                'client_name' => $row['client_name'] ?? null,
                'client_id' => $row['client_id'] ?? null,
                'invoice_ref' => $row['invoice_ref'] ?? null
            ];
        }

        return $payments;
    }
}
