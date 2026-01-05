<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

class AccountingTransactionsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère la liste des encaissements d'un mois
     *
     * @param int $year Année (ex: 2025)
     * @param int $month Mois (1-12)
     * @param int $limit Nombre maximum de lignes à retourner
     * @return array ['transactions' => array, 'truncated' => bool, 'total' => float]
     */
    public function getMonthlyTransactions(int $year, int $month, int $limit = 1000): array
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

        // Requête : récupérer les transactions
        // Note : Pas de champ d'annulation détecté dans la table reglements
        $sql = "SELECT 
                    r.paid_at,
                    r.amount,
                    r.method,
                    c.name as client_name,
                    f.numero as invoice_ref
                 FROM reglements r
                 LEFT JOIN factures f ON r.facture_id = f.id
                 LEFT JOIN clients c ON f.client_id = c.id
                 WHERE r.paid_at >= :start AND r.paid_at < :end
                 ORDER BY r.paid_at ASC
                 LIMIT :limit_plus_one";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':end', $endDate, PDO::PARAM_STR);
        $stmt->bindValue(':limit_plus_one', $limit + 1, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Vérifier s'il y a plus de lignes que la limite
        $truncated = count($rows) > $limit;
        
        // Couper à la limite si nécessaire
        if ($truncated) {
            $rows = array_slice($rows, 0, $limit);
        }

        // Formater les données et calculer le total
        $transactions = [];
        $total = 0;
        foreach ($rows as $row) {
            // Formatage de la date avec heure
            $date = new \DateTime($row['paid_at']);
            
            $transactions[] = [
                'date' => $date->format('d/m/Y H:i'),
                'amount' => (float)$row['amount'],
                'method' => $row['method'] ?? '—',
                'client_name' => $row['client_name'] ?? null,
                'invoice_ref' => $row['invoice_ref'] ?? null
            ];
            
            // Ajouter au total
            $total += (float)$row['amount'];
        }

        return [
            'transactions' => $transactions,
            'truncated' => $truncated,
            'total' => $total
        ];
    }
}
