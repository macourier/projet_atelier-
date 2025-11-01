<?php
declare(strict_types=1);

namespace App\Service;

use PDO;
use Throwable;

class NumberingService
{
    private ?PDO $pdo;
    private string $defaultPrefix;

    public function __construct(?PDO $pdo = null, string $defaultPrefix = '')
    {
        $this->pdo = $pdo;
        $this->defaultPrefix = $defaultPrefix;
    }

    /**
     * Get next number for a sequence name (e.g. 'facture').
     * Returns generated identifier: prefix + zero-padded number (if prefix provided).
     *
     * @param string $name
     * @param int $pad number padding (e.g. 4 -> 0001)
     * @return string
     */
    public function next(string $name, int $pad = 4): string
    {
        $prefix = $this->defaultPrefix;
        if ($this->pdo) {
            try {
                $this->pdo->beginTransaction();

                $stmt = $this->pdo->prepare('SELECT prefix, last_number FROM sequences WHERE name = :name LIMIT 1');
                $stmt->execute(['name' => $name]);
                $row = $stmt->fetch();

                if ($row) {
                    $prefix = $row['prefix'] ?? $prefix;
                    $last = (int)$row['last_number'] + 1;
                    $update = $this->pdo->prepare('UPDATE sequences SET last_number = :last_number WHERE name = :name');
                    $update->execute(['last_number' => $last, 'name' => $name]);
                } else {
                    // Insert new sequence starting at 1
                    $last = 1;
                    $insert = $this->pdo->prepare('INSERT INTO sequences (name, prefix, last_number) VALUES (:name, :prefix, :last_number)');
                    $insert->execute(['name' => $name, 'prefix' => $prefix, 'last_number' => $last]);
                }

                $this->pdo->commit();
                return ($prefix ?? '') . str_pad((string)$last, $pad, '0', STR_PAD_LEFT);
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                // fallback to non-persistent sequence
            }
        }

        // Fallback if DB not available: use time-based incremental
        $num = (int)round(microtime(true) * 1000) % (int)pow(10, $pad);
        return ($prefix ?? '') . str_pad((string)$num, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Read current sequence value without incrementing.
     *
     * @param string $name
     * @return int|null
     */
    public function current(string $name): ?int
    {
        if (!$this->pdo) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT last_number FROM sequences WHERE name = :name LIMIT 1');
            $stmt->execute(['name' => $name]);
            $row = $stmt->fetch();
            return $row ? (int)$row['last_number'] : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
