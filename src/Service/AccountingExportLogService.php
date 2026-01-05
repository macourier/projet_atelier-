<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

class AccountingExportLogService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Vérifie si la table accounting_exports existe
     *
     * @return bool
     */
    private function tableExists(): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='accounting_exports'");
            $stmt->execute();
            return $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Crée la table accounting_exports si elle n'existe pas
     *
     * @return void
     */
    private function createTableIfNotExists(): void
    {
        if ($this->tableExists()) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS accounting_exports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            year INTEGER NOT NULL,
            month INTEGER NOT NULL,
            exported_at TEXT NOT NULL,
            exported_by TEXT NULL,
            row_count INTEGER NULL
        )";

        $this->pdo->exec($sql);

        // Créer les index
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_accounting_exports_year_month ON accounting_exports (year, month)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_accounting_exports_exported_at ON accounting_exports (exported_at DESC)");
    }

    /**
     * Enregistre un export comptable mensuel
     *
     * @param int $year Année de l'export
     * @param int $month Mois de l'export (1-12)
     * @param string|null $exportedBy Nom d'utilisateur qui a effectué l'export (optionnel)
     * @param int|null $rowCount Nombre de lignes exportées (optionnel)
     * @return void
     */
    public function logMonthlyExport(int $year, int $month, ?string $exportedBy, ?int $rowCount): void
    {
        // Créer la table si elle n'existe pas (self-healing)
        $this->createTableIfNotExists();

        $sql = "INSERT INTO accounting_exports (year, month, exported_at, exported_by, row_count)
                VALUES (:year, :month, :exported_at, :exported_by, :row_count)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':month', $month, PDO::PARAM_INT);
        $stmt->bindValue(':exported_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':exported_by', $exportedBy, PDO::PARAM_STR);
        $stmt->bindValue(':row_count', $rowCount, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Récupère le dernier export comptable pour un mois donné
     *
     * @param int $year Année
     * @param int $month Mois (1-12)
     * @return array|null Dernier export avec clés : exported_at, exported_by, row_count, ou null si aucun
     */
    public function getLastMonthlyExport(int $year, int $month): ?array
    {
        // Si la table n'existe pas, retourner null silencieusement
        if (!$this->tableExists()) {
            return null;
        }

        $sql = "SELECT exported_at, exported_by, row_count
                FROM accounting_exports
                WHERE year = :year AND month = :month
                ORDER BY exported_at DESC
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':month', $month, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
}
