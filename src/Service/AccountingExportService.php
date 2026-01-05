<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

class AccountingExportService
{
    private PDO $pdo;
    private AccountingKpiService $kpiService;

    public function __construct(PDO $pdo, AccountingKpiService $kpiService)
    {
        $this->pdo = $pdo;
        $this->kpiService = $kpiService;
    }

    /**
     * Génère un export comptable mensuel (XLSX)
     *
     * @param int $year Année (ex: 2025)
     * @param int $month Mois (1-12)
     * @return array ['filename' => string, 'contentType' => string, 'content' => string, 'row_count' => int]
     */
    public function exportMonthly(int $year, int $month): array
    {
        return $this->buildMonthlyXlsx($year, $month);
    }

    /**
     * Construit l'export mensuel XLSX selon les spécifications comptables
     *
     * @param int $year Année (ex: 2025)
     * @param int $month Mois (1-12)
     * @return array ['filename' => string, 'contentType' => string, 'content' => string, 'row_count' => int]
     * @throws \Exception Si plus de 5000 lignes
     */
    public function buildMonthlyXlsx(int $year, int $month): array
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

        // Récupérer les transactions
        $transactions = $this->getTransactions($startDate, $endDate);

        // Garde-fou : limite à 5000 lignes
        if (count($transactions) > 5000) {
            throw new \Exception('Trop de transactions pour l\'export (' . count($transactions) . ' > 5000). Veuillez exporter par périodes plus courtes.');
        }

        // Créer le fichier XLSX
        $filename = sprintf('export-comptable-%04d-%02d.xlsx', $year, $month);
        $content = $this->generateAccountingXlsx($transactions);
        $rowCount = count($transactions);

        return [
            'filename' => $filename,
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content' => $content,
            'row_count' => $rowCount
        ];
    }

    /**
     * Récupère les transactions de la période
     */
    private function getTransactions(string $startDate, string $endDate): array
    {
        // Vérifier si un champ d'annulation existe dans la table reglements
        $hasVoidField = $this->hasVoidField();

        $sql = "SELECT 
                    r.paid_at,
                    r.amount,
                    r.method,
                    c.name as client_name,
                    f.numero as invoice_ref
                 FROM reglements r
                 LEFT JOIN factures f ON r.facture_id = f.id
                 LEFT JOIN clients c ON f.client_id = c.id
                 WHERE r.paid_at >= :start AND r.paid_at < :end";

        // Ajouter le filtre d'annulation si le champ existe
        if ($hasVoidField) {
            $sql .= " AND (r.is_void = 0 OR r.is_void IS NULL)";
            $sql .= " AND (r.deleted_at IS NULL)";
        }

        $sql .= " ORDER BY r.paid_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':end', $endDate, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie si des champs d'annulation existent dans la table reglements
     */
    private function hasVoidField(): bool
    {
        try {
            $cols = $this->pdo->query("PRAGMA table_info(reglements)")->fetchAll(PDO::FETCH_ASSOC);
            $colNames = array_map(fn($c) => strtolower($c['name'] ?? ''), $cols);
            return in_array('is_void', $colNames) || in_array('deleted_at', $colNames) || in_array('is_deleted', $colNames);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Génère le fichier XLSX comptable avec PhpSpreadsheet
     * Format : 1 seul onglet "Encaissements", colonnes spécifiées, ligne TOTAL
     */
    private function generateAccountingXlsx(array $transactions): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // ===== ONGLET UNIQUE : ENCAISSEMENTS =====
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Encaissements');

        // Définition des largeurs de colonnes
        $sheet->getColumnDimension('A')->setWidth(12); // Date
        $sheet->getColumnDimension('B')->setWidth(8);  // Heure
        $sheet->getColumnDimension('C')->setWidth(12); // Montant
        $sheet->getColumnDimension('D')->setWidth(18); // Moyen de paiement
        $sheet->getColumnDimension('E')->setWidth(22); // Client
        $sheet->getColumnDimension('F')->setWidth(14); // Référence
        $sheet->getColumnDimension('G')->setWidth(22); // Commentaire

        // En-têtes (ligne 1)
        $sheet->setCellValue('A1', 'Date');
        $sheet->setCellValue('B1', 'Heure');
        $sheet->setCellValue('C1', 'Montant (TTC)');
        $sheet->setCellValue('D1', 'Moyen de paiement');
        $sheet->setCellValue('E1', 'Client');
        $sheet->setCellValue('F1', 'Référence');
        $sheet->setCellValue('G1', 'Commentaire');

        // Style des en-têtes : gras + fond léger
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'F3F4F6']
            ]
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

        // Données
        $row = 2;
        $totalAmount = 0;

        foreach ($transactions as $tx) {
            $date = new \DateTime($tx['paid_at']);
            
            $sheet->setCellValue('A' . $row, $date->format('d/m/Y'));
            $sheet->setCellValue('B' . $row, $date->format('H:i'));
            
            // Montant TTC au format nombre Excel
            $amount = (float)$tx['amount'];
            $sheet->setCellValue('C' . $row, $amount);
            
            $sheet->setCellValue('D' . $row, $tx['method'] ?? '—');
            $sheet->setCellValue('E' . $row, $tx['client_name'] ?? '—');
            $sheet->setCellValue('F' . $row, $tx['invoice_ref'] ?? '—');
            $sheet->setCellValue('G' . $row, ''); // Commentaire vide
            
            $totalAmount += $amount;
            $row++;
        }

        // Format nombre pour les montants (2 décimales)
        $sheet->getStyle('C2:C' . ($row - 1))->getNumberFormat()->setFormatCode('0.00');

        // Ligne TOTAL (si on a des données)
        if (count($transactions) > 0) {
            $sheet->setCellValue('A' . $row, 'TOTAL');
            $sheet->setCellValue('C' . $row, $totalAmount);
            
            // Style du TOTAL : gras
            $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
            
            // Format nombre pour le total
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('0.00');
        }

        // Générer le fichier
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Sauvegarder dans un fichier temporaire
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tmp);
        
        // Lire le contenu
        $content = file_get_contents($tmp);
        
        // Nettoyer
        @unlink($tmp);
        
        return $content;
    }
}
