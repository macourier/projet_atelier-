<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

class AccountingKpiService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calcule les KPI mensuels pour la comptabilité
     *
     * @param int $year Année (ex: 2025)
     * @param int $month Mois (1-12)
     * @return array ['ca_eur' => float, 'clients' => int, 'avg_basket_eur' => float]
     */
    public function getMonthlyKpis(int $year, int $month): array
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

        // Requête CA : somme des montants encaissés
        // Note : Pas de champ d'annulation détecté dans la table reglements
        $caSql = "SELECT COALESCE(SUM(r.amount), 0)
                  FROM reglements r
                  JOIN factures f ON r.facture_id = f.id
                  WHERE r.paid_at >= :start AND r.paid_at < :end";
        $stmt = $this->pdo->prepare($caSql);
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        $caEur = (float)$stmt->fetchColumn();

        // Requête Clients : nombre de clients uniques
        // Note : Pas de champ d'annulation détecté dans la table reglements
        $clientsSql = "SELECT COUNT(DISTINCT f.client_id)
                      FROM reglements r
                      JOIN factures f ON r.facture_id = f.id
                      WHERE r.paid_at >= :start AND r.paid_at < :end
                      AND f.client_id IS NOT NULL";
        $stmt = $this->pdo->prepare($clientsSql);
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        $clients = (int)$stmt->fetchColumn();

        // Calcul panier moyen
        $avgBasketEur = ($clients > 0) ? ($caEur / $clients) : 0;

        return [
            'ca_eur' => $caEur,
            'clients' => $clients,
            'avg_basket_eur' => $avgBasketEur
        ];
    }

    /**
     * Calcule la ventilation des encaissements par moyen de paiement
     *
     * @param int $year Année (ex: 2025)
     * @param int $month Mois (1-12)
     * @return array ['CB' => float, 'ESPECES' => float, 'VIREMENT' => float, 'AUTRES' => float]
     */
    public function getMonthlyPaymentBreakdown(int $year, int $month): array
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

        // Requête : somme des montants par méthode de paiement
        // Note : Pas de champ d'annulation détecté dans la table reglements
        $sql = "SELECT method, COALESCE(SUM(r.amount), 0) as total
                FROM reglements r
                JOIN factures f ON r.facture_id = f.id
                WHERE r.paid_at >= :start AND r.paid_at < :end
                GROUP BY method";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Initialiser les résultats
        $breakdown = [
            'CB' => 0.0,
            'ESPECES' => 0.0,
            'VIREMENT' => 0.0,
            'AUTRES' => 0.0
        ];

        // Mapper les valeurs DB vers les catégories fonctionnelles
        foreach ($rows as $row) {
            $method = strtoupper(trim($row['method']));
            $total = (float)$row['total'];

            // Mapping des méthodes
            if (in_array($method, ['CB', 'CARTE', 'BANQUE'])) {
                $breakdown['CB'] += $total;
            } elseif (in_array($method, ['ESPECE', 'ESPÈCES', 'ESPECES'])) {
                $breakdown['ESPECES'] += $total;
            } elseif (in_array($method, ['VIREMENT', 'VIR'])) {
                $breakdown['VIREMENT'] += $total;
            } else {
                // Tout autre valeur va dans AUTRES
                $breakdown['AUTRES'] += $total;
            }
        }

        return $breakdown;
    }
}
