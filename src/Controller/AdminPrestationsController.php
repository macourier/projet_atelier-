<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;

class AdminPrestationsController
{
    private array $container;
    private ?PDO $pdo;
    private $twig;

    public function __construct(array $container = [])
    {
        $this->container = $container;
        $this->pdo = $container['pdo'] ?? null;
        $this->twig = $container['twig'] ?? null;
        if (!isset($_SESSION)) {
            @session_start();
        }
    }

    public function index(Request $request, Response $response): Response
    {
        $rows = [];
        $pieceSupported = false;
        $debugCols = [];
        $dbList = [];
        if ($this->pdo) {
            // Détection robuste via PRAGMA table_info (évite faux positifs du try/catch)
            try {
                $cols = $this->pdo->query("PRAGMA table_info(prestations_catalogue)")->fetchAll(PDO::FETCH_ASSOC);
                // Collecte des noms de colonnes pour debug
                $debugCols = [];
                foreach ($cols as $cc) {
                    $debugCols[] = strtolower($cc['name'] ?? '');
                }
                // Chemins DB réellement ouverts par SQLite (main -> file)
                $dbList = $this->pdo->query("PRAGMA database_list")->fetchAll(PDO::FETCH_ASSOC);
                $hasLib = false; $hasPrix = false;
                foreach ($cols as $c) {
                    $n = strtolower($c['name'] ?? '');
                    if ($n === 'piece_libelle') $hasLib = true;
                    if ($n === 'piece_prix_ht') $hasPrix = true;
                }
                $pieceSupported = ($hasLib && $hasPrix);

                // Auto-réparation: si les colonnes n'existent pas, tenter de les ajouter et re-vérifier
                if (!$pieceSupported) {
                    try {
                        $this->pdo->exec('PRAGMA foreign_keys = OFF');
                        if (!$hasLib) {
                            $this->pdo->exec("ALTER TABLE prestations_catalogue ADD COLUMN piece_libelle TEXT");
                        }
                        if (!$hasPrix) {
                            $this->pdo->exec("ALTER TABLE prestations_catalogue ADD COLUMN piece_prix_ht REAL");
                        }
                        // Backfill valeurs par défaut
                        $this->pdo->exec("UPDATE prestations_catalogue SET piece_libelle = COALESCE(piece_libelle, 'Pièce')");
                        $this->pdo->exec("UPDATE prestations_catalogue SET piece_prix_ht = COALESCE(piece_prix_ht, 0)");
                    } catch (\Throwable $patchErr) {
                        // ignorer, on restera en mode compat
                    } finally {
                        try { $this->pdo->exec('PRAGMA foreign_keys = ON'); } catch (\Throwable $ignore) {}
                    }
                    // Re-vérifier PRAGMA après tentative
                    try {
                        $cols2 = $this->pdo->query("PRAGMA table_info(prestations_catalogue)")->fetchAll(PDO::FETCH_ASSOC);
                        $debugCols = [];
                        $hasLib = false; $hasPrix = false;
                        foreach ($cols2 as $cc2) {
                            $name = strtolower($cc2['name'] ?? '');
                            $debugCols[] = $name;
                            if ($name === 'piece_libelle') $hasLib = true;
                            if ($name === 'piece_prix_ht') $hasPrix = true;
                        }
                        $pieceSupported = ($hasLib && $hasPrix);
                    } catch (\Throwable $ignore) {}
                }
            } catch (\Throwable $e) {
                $pieceSupported = false;
            }

            if ($pieceSupported) {
                $stmt = $this->pdo->query("SELECT id, categorie, libelle, prix_main_oeuvre_ht, COALESCE(piece_libelle,'Pièce') AS piece_libelle, COALESCE(piece_prix_ht,0) AS piece_prix_ht, COALESCE(tva_pct,20.0) AS tva_pct, COALESCE(duree_min,15) AS duree_min, deleted_at FROM prestations_catalogue ORDER BY categorie, libelle");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->pdo->query("SELECT id, categorie, libelle, prix_main_oeuvre_ht, COALESCE(tva_pct,20.0) AS tva_pct, COALESCE(duree_min,15) AS duree_min, deleted_at FROM prestations_catalogue ORDER BY categorie, libelle");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        // Build grouped by categorie for accordion UI
        $grouped = [];
        foreach ($rows as $r) {
            $cat = (string)($r['categorie'] ?? 'Autre');
            if (!isset($grouped[$cat])) { $grouped[$cat] = []; }
            $grouped[$cat][] = $r;
        }
        // Charger la liste des catégories (pour suppression via select)
        $cats = [];
        if ($this->pdo) {
            try {
                $stc = $this->pdo->query("SELECT id, name FROM categories ORDER BY name");
                $cats = $stc->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $cats = [];
            }
        }
        // Fusionner catégories en sections vides si absentes des prestations
        if ($cats) {
            foreach ($cats as $c) {
                $n = (string)($c['name'] ?? '');
                if ($n !== '' && !isset($grouped[$n])) {
                    $grouped[$n] = [];
                }
            }
        }

        $html = $this->twig->render('admin/prestations.twig', [
            'prestations' => $rows,
            'grouped_prestas' => $grouped,
            'piece_supported' => $pieceSupported,
            'debug_cols' => $debugCols,
            'db_list' => $dbList,
            'categories' => $cats,
            'env' => ($this->container['env'] ?? $_ENV ?? [])
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function create(Request $request, Response $response): Response
    {
        if (!$this->pdo) return $response->withStatus(500);
        $d = (array)$request->getParsedBody();

        $hasCategoryId = $this->hasCategoryIdColumn();

        // Resolve category only if the column exists
        $categoryId = null;
        if ($hasCategoryId) {
            if (isset($d['category_id']) && trim((string)$d['category_id']) !== '') {
                $categoryId = (int)$d['category_id'];
            } elseif (!empty($d['categorie'])) {
                $categoryId = $this->resolveCategoryId(trim((string)$d['categorie']));
            }
        }

        $lib = trim($d['libelle'] ?? '');
        $prixStr = trim((string)($d['prix_main_oeuvre_ht'] ?? '0'));
        $prixStr = str_replace(',', '.', $prixStr);
        $prix = (float)$prixStr;

        // Toujours exiger un libellé
        if ($lib === '') {
            return $response->withStatus(400);
        }
        // Si la colonne category_id existe, exiger une catégorie valide
        if ($hasCategoryId && ($categoryId === null || $categoryId === 0)) {
            return $response->withStatus(400);
        }

        // Générer un id s'il n'est pas fourni (schéma: id TEXT PRIMARY KEY)
        $id = trim($d['id'] ?? '');
        if ($id === '') {
            $id = $this->generateRandomPrestId();
        }

        // Valeurs par défaut (non exposées à l'UI simplifiée)
        $tva = 20.0;
        $dur = 15;

        // Champs Pièce (optionnels côté UI)
        $pieceLib = trim($d['piece_libelle'] ?? 'Pièce');
        $piecePrixStr = trim((string)($d['piece_prix_ht'] ?? '0'));
        $piecePrixStr = str_replace(',', '.', $piecePrixStr);
        $piecePrix = (float)$piecePrixStr;

        if ($hasCategoryId) {
            $sql = "INSERT INTO prestations_catalogue (id, categorie, category_id, libelle, prix_main_oeuvre_ht, piece_libelle, piece_prix_ht, tva_pct, duree_min) 
                    VALUES (:id,:c,:cid,:l,:p,:pl,:pp,:t,:d)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'c' => $d['categorie'] ?? '',
                'cid' => $categoryId,
                'l' => $lib,
                'p' => $prix,
                'pl' => $pieceLib,
                'pp' => $piecePrix,
                't' => $tva,
                'd' => $dur
            ]);
        } else {
            // Fallback sans colonne category_id : on stocke seulement la catégorie texte
            $sql = "INSERT INTO prestations_catalogue (id, categorie, libelle, prix_main_oeuvre_ht, piece_libelle, piece_prix_ht, tva_pct, duree_min) 
                    VALUES (:id,:c,:l,:p,:pl,:pp,:t,:d)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'c' => $d['categorie'] ?? '',
                'l' => $lib,
                'p' => $prix,
                'pl' => $pieceLib,
                'pp' => $piecePrix,
                't' => $tva,
                'd' => $dur
            ]);
        }

        return $response->withHeader('Location','/admin/prestations')->withStatus(302);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) return $response->withStatus(500);
        $pid = $args['id'] ?? null;
        if (!$pid) return $response->withStatus(400);

        $hasCategoryId = $this->hasCategoryIdColumn();

        $d = (array)$request->getParsedBody();
        // On accepte uniquement ces champs malgré l'UI simplifiée
        $fields = ['categorie','libelle','prix_main_oeuvre_ht','piece_libelle','piece_prix_ht','tva_pct','duree_min'];
        if ($hasCategoryId) {
            $fields[] = 'category_id';
        }
        $sets = [];
        $params = ['id' => $pid];
        foreach ($fields as $f) {
            if (array_key_exists($f, $d)) {
                if ($f === 'categorie') {
                    $catName = trim((string)$d[$f]);
                    if ($hasCategoryId) {
                        // resolve category name to id and set both fields
                        $catId = $this->resolveCategoryId($catName);
                        $sets[] = "categorie = :categorie";
                        $sets[] = "category_id = :category_id";
                        $params['categorie'] = $catName;
                        $params['category_id'] = $catId;
                    } else {
                        // Pas de colonne category_id : on ne met à jour que le texte
                        $sets[] = "categorie = :categorie";
                        $params['categorie'] = $catName;
                    }
                } else {
                    $sets[] = "$f = :$f";
                    if ($f === 'libelle' || $f === 'piece_libelle') {
                        $params[$f] = trim((string)$d[$f]);
                    } elseif ($f === 'duree_min') {
                        $params[$f] = (int)$d[$f];
                    } else {
                        $val = trim((string)$d[$f]);
                        $val = str_replace(',', '.', $val);
                        if ($f === 'category_id') {
                            $params[$f] = (int)$val;
                        } else {
                            $params[$f] = (float)$val;
                        }
                    }
                }
            }
        }
        if (!$sets) return $response->withStatus(400);

        $sql = "UPDATE prestations_catalogue SET ".implode(',', $sets).", deleted_at = deleted_at WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // Auto-save OK
        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) return $response->withStatus(500);
        $pid = $args['id'] ?? null;
        if (!$pid) return $response->withStatus(400);

        // Soft delete: set deleted_at
        $stmt = $this->pdo->prepare("UPDATE prestations_catalogue SET deleted_at = datetime('now') WHERE id = :id");
        $stmt->execute(['id'=>$pid]);

        // Stocker pour undo simple (5s)
        $_SESSION['last_deleted_prest_id'] = $pid;
        $_SESSION['last_deleted_time'] = time();

        return $response->withHeader('Location','/admin/prestations')->withStatus(302);
    }

    public function undo(Request $request, Response $response): Response
    {
        if (!$this->pdo) return $response->withStatus(500);
        $pid = $_SESSION['last_deleted_prest_id'] ?? null;
        $t = $_SESSION['last_deleted_time'] ?? 0;
        if ($pid && (time() - $t) <= 5) {
            $stmt = $this->pdo->prepare("UPDATE prestations_catalogue SET deleted_at = NULL WHERE id = :id");
            $stmt->execute(['id'=>$pid]);
            unset($_SESSION['last_deleted_prest_id'], $_SESSION['last_deleted_time']);
            return $response->withHeader('Location','/admin/prestations')->withStatus(302);
        }
        $response->getBody()->write('Undo expiré');
        return $response->withStatus(410);
    }

    public function export(Request $request, Response $response): Response
    {
        if (!$this->pdo) return $response->withStatus(500);
        $stmt = $this->pdo->query("SELECT id,categorie,libelle,prix_main_oeuvre_ht,tva_pct,duree_min FROM prestations_catalogue WHERE deleted_at IS NULL ORDER BY categorie, libelle");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Générer XLSX via PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['id','categorie','libelle','prix_main_oeuvre_ht','tva_pct','duree_min'], null, 'A1');
        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([$row['id'],$row['categorie'],$row['libelle'],$row['prix_main_oeuvre_ht'],$row['tva_pct'],$row['duree_min']], null, 'A'.$r++);
        }
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        // Stream response
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tmp);
        $stream = fopen($tmp, 'rb');
        $body = $response->getBody();
        $body->write(stream_get_contents($stream));
        fclose($stream);
        @unlink($tmp);

        return $response
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('Content-Disposition', 'attachment; filename=\"prestations.xlsx\"')
            ->withStatus(200);
    }

    /**
     * Indique si la colonne category_id existe dans prestations_catalogue.
     * Permet de garder la compatibilité avec d'anciennes bases non migrées.
     */
    private function hasCategoryIdColumn(): bool
    {
        if (!$this->pdo) {
            return false;
        }
        try {
            $cols = $this->pdo->query("PRAGMA table_info(prestations_catalogue)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                $n = strtolower($c['name'] ?? '');
                if ($n === 'category_id') {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * Résout l'id d'une catégorie en base en créant la catégorie si nécessaire.
     */
    private function resolveCategoryId(string $name): int
    {
        if (!$this->pdo) {
            return 0;
        }
        $name = trim($name);
        if ($name === '') return 0;
        $stmt = $this->pdo->prepare("SELECT id FROM categories WHERE name = :n LIMIT 1");
        $stmt->execute(['n' => $name]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $ins = $this->pdo->prepare("INSERT INTO categories (name) VALUES (:n)");
        $ins->execute(['n' => $name]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Crée une catégorie via la tuile d'ajout (POST /admin/categories)
     */
    public function createCategory(Request $request, Response $response): Response
    {
        if (!$this->pdo) return $response->withStatus(500);
        $d = (array)$request->getParsedBody();
        $name = trim($d['name'] ?? '');
        if ($name === '') {
            return $response->withStatus(400);
        }
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO categories (name) VALUES (:n)");
        $stmt->execute(['n' => $name]);
        return $response->withHeader('Location','/admin/prestations?cat_added=1&cat_name=' . rawurlencode($name))->withStatus(302);
    }

    /**
     * Génère un identifiant unique de prestation (id TEXT) de la forme PREST_XXXXXX.
     */
    private function generateRandomPrestId(): string
    {
        if (!$this->pdo) {
            return 'PREST_' . strtoupper(bin2hex(random_bytes(3)));
        }
        $tries = 0;
        do {
            $candidate = 'PREST_' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $this->pdo->prepare("SELECT 1 FROM prestations_catalogue WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $candidate]);
            $exists = (bool)$stmt->fetchColumn();
            $tries++;
            if (!$exists) {
                return $candidate;
            }
        } while ($tries < 10);
        // fallback très improbable
        return 'PREST_' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Supprime une catégorie et toutes les prestations associées (définitif).
     * Route: POST /admin/categories/{id}/delete
     */
    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) return $response->withStatus(500);
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) return $response->withStatus(400);

        try {
            $this->pdo->beginTransaction();
            // Récupérer le nom de la catégorie (pour compat si certaines lignes n'ont pas category_id)
            $name = '';
            $st = $this->pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
            $st->execute(['id' => $id]);
            $name = (string)($st->fetchColumn() ?: '');

            // Supprimer prestations associées (par id ou par nom)
            if ($this->hasCategoryIdColumn()) {
                $dp = $this->pdo->prepare("DELETE FROM prestations_catalogue WHERE category_id = :id OR categorie = :n");
                $dp->execute(['id' => $id, 'n' => $name]);
            } else {
                $dp = $this->pdo->prepare("DELETE FROM prestations_catalogue WHERE categorie = :n");
                $dp->execute(['n' => $name]);
            }

            // Supprimer la catégorie
            $dc = $this->pdo->prepare("DELETE FROM categories WHERE id = :id");
            $dc->execute(['id' => $id]);

            $this->pdo->commit();
            return $response->withHeader('Location','/admin/prestations')->withStatus(302);
        } catch (\Throwable $e) {
            try { $this->pdo->rollBack(); } catch (\Throwable $ignore) {}
            return $response->withStatus(500);
        }
    }

}
