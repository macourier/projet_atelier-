<?php
declare(strict_types=1);

namespace App\Service;

use PDO;

class TicketService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function loadCatalogueGrouped(): array
    {
        // Group prestations by categorie (fallback si colonnes piece_* absentes)
        try {
            $stmt = $this->pdo->query("SELECT id, TRIM(categorie) AS categorie, libelle, prix_main_oeuvre_ht, COALESCE(piece_libelle,'Pièce') AS piece_libelle, COALESCE(piece_prix_ht,0) AS piece_prix_ht, COALESCE(tva_pct,20.0) AS tva_pct, COALESCE(duree_min,15) AS duree_min FROM prestations_catalogue WHERE deleted_at IS NULL ORDER BY TRIM(categorie), libelle");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Base non migrée: revenir à l'ancien SELECT et compléter les champs
            $stmt = $this->pdo->query("SELECT id, TRIM(categorie) AS categorie, libelle, prix_main_oeuvre_ht, COALESCE(tva_pct,20.0) AS tva_pct, COALESCE(duree_min,15) AS duree_min FROM prestations_catalogue WHERE deleted_at IS NULL ORDER BY TRIM(categorie), libelle");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['piece_libelle'] = 'Pièce';
                $r['piece_prix_ht'] = 0.0;
            }
            unset($r);
        }
        $grouped = [];
        foreach ($rows as $r) {
            $raw = (string)($r['categorie'] ?? '');
            $norm = strtolower(trim($raw));
            if ($norm === '') { $norm = 'autre'; }
            if (!isset($grouped[$norm])) {
                $display = ($raw !== '' ? trim($raw) : 'Autre');
                $grouped[$norm] = ['__display' => $display, '__items' => []];
            }
            $grouped[$norm]['__items'][] = $r;
        }
        // Re-projeter en map "label => items" attendue par les templates
        $out = [];
        foreach ($grouped as $g) {
            $out[$g['__display']] = $g['__items'];
        }
        return $out;
    }

    public function computeTotals(int $ticketId): array
    {
        // Sum prestations (HT uniquement)
        $sum = $this->pdo->prepare("SELECT 
            COALESCE(SUM(quantite * prix_ht_snapshot),0) AS total_ht
            FROM ticket_prestations WHERE ticket_id = :tid");
        $sum->execute(['tid' => $ticketId]);
        $a = $sum->fetch(PDO::FETCH_ASSOC) ?: ['total_ht' => 0];

        // Sum consommables (HT uniquement)
        $sum2 = $this->pdo->prepare("SELECT 
            COALESCE(SUM(quantite * prix_ht_snapshot),0) AS total_ht
            FROM ticket_consommables WHERE ticket_id = :tid");
        $sum2->execute(['tid' => $ticketId]);
        $b = $sum2->fetch(PDO::FETCH_ASSOC) ?: ['total_ht' => 0];

        $total_ht = (float)$a['total_ht'] + (float)$b['total_ht'];
        $total_tva = 0.0; // Pas de TVA
        $total_ttc = $total_ht; // TOTAL = HT

        // Persist totals on ticket
        $upd = $this->pdo->prepare("UPDATE tickets SET total_ht = :ht, total_tva = :tva, total_ttc = :ttc, updated_at = CURRENT_TIMESTAMP WHERE id = :tid");
        $upd->execute(['ht' => $total_ht, 'tva' => $total_tva, 'ttc' => $total_ttc, 'tid' => $ticketId]);

        return ['ht' => $total_ht, 'tva' => $total_tva, 'ttc' => $total_ttc];
    }

    public function replacePrestationsFromPost(int $ticketId, array $post): void
    {
        // Inputs attendus (possiblement multiples et mixés MO/Pièce pour un même prest_id)
        $prestIds = $post['prest_id'] ?? [];
        $qtys = $post['qty'] ?? [];
        $priceOverrides = $post['price_override'] ?? [];
        $pieceQtys = $post['piece_qty'] ?? [];
        $piecePriceOverrides = $post['piece_price_override'] ?? [];

        // 1) Construire un agrégat des entrées postées (fusion par libellé + prix + TVA)
        //    Clé d'agrégat: type: 'mo' | 'piece'
        //    Pour les prestations: key = "mo|{label}|{price}|{tva}"
        //    Pour les pièces:      key = "piece|{label}|{price}|{tva}" (label 'Pièce')
        $pending = [
            'mo' => [],     // key => ['label','qty','price','tva']
            'piece' => []   // key => ['label','qty','price','tva']
        ];

        foreach ($prestIds as $idx => $pid) {
            $pid = trim((string)$pid);
            if ($pid === '') {
                continue;
            }

            // Charger la prestation depuis le catalogue (fallback si base non migrée)
            try {
                $sel = $this->pdo->prepare("SELECT libelle, prix_main_oeuvre_ht, COALESCE(piece_libelle,'Pièce') AS piece_libelle, COALESCE(piece_prix_ht,0) AS piece_prix_ht, COALESCE(tva_pct,20.0) AS tva_pct FROM prestations_catalogue WHERE id = :id AND deleted_at IS NULL LIMIT 1");
                $sel->execute(['id' => $pid]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);
            } catch (\PDOException $ex) {
                // Fallback: anciennes bases sans colonnes piece_*
                $sel = $this->pdo->prepare("SELECT libelle, prix_main_oeuvre_ht, COALESCE(tva_pct,20.0) AS tva_pct FROM prestations_catalogue WHERE id = :id AND deleted_at IS NULL LIMIT 1");
                $sel->execute(['id' => $pid]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $row['piece_libelle'] = 'Pièce';
                    $row['piece_prix_ht'] = 0.0;
                }
            }
            if (!$row) {
                continue;
            }

            $label = (string)$row['libelle'];
            $tva = (float)$row['tva_pct'];
            $basePrice = (float)$row['prix_main_oeuvre_ht'];

            // MO
            $moQty = isset($qtys[$idx]) ? max(0, (int)$qtys[$idx]) : 0;
            $moPrice = isset($priceOverrides[$idx]) && $priceOverrides[$idx] !== '' ? (float)$priceOverrides[$idx] : $basePrice;
            if ($moQty > 0) {
                $key = 'mo|' . $label . '|' . $moPrice . '|' . $tva;
                if (!isset($pending['mo'][$key])) {
                    $pending['mo'][$key] = ['label' => $label, 'qty' => 0, 'price' => $moPrice, 'tva' => $tva];
                }
                $pending['mo'][$key]['qty'] += $moQty;
            }

            // Pièce (consommable custom)
            $pQty = isset($pieceQtys[$idx]) ? max(0, (int)$pieceQtys[$idx]) : 0;
            $basePiece = isset($row['piece_prix_ht']) ? (float)$row['piece_prix_ht'] : 0.0;
            $pPrice = isset($piecePriceOverrides[$idx]) && $piecePriceOverrides[$idx] !== '' ? (float)$piecePriceOverrides[$idx] : $basePiece;
            if ($pQty > 0) {
                $pLabel = (string)($row['piece_libelle'] ?? 'Pièce');
                $key = 'piece|' . $pLabel . '|' . $pPrice . '|' . $tva;
                if (!isset($pending['piece'][$key])) {
                    $pending['piece'][$key] = ['label' => $pLabel, 'qty' => 0, 'price' => $pPrice, 'tva' => $tva];
                }
                $pending['piece'][$key]['qty'] += $pQty;
            }
        }

        // 2) Charger l'existant et indexer par clé de fusion (même logique)
        $existingMo = [];     // key => ['id','qty']
        $stmt = $this->pdo->prepare("SELECT id, label, quantite, prix_ht_snapshot, tva_snapshot FROM ticket_prestations WHERE ticket_id = :tid");
        $stmt->execute(['tid' => $ticketId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = 'mo|' . (string)$r['label'] . '|' . (float)$r['prix_ht_snapshot'] . '|' . (float)$r['tva_snapshot'];
            $existingMo[$k] = ['id' => (int)$r['id'], 'qty' => (int)$r['quantite']];
        }

        $existingPiece = [];  // key => ['id','qty']
        $stmt = $this->pdo->prepare("SELECT id, label, quantite, prix_ht_snapshot, tva_snapshot FROM ticket_consommables WHERE ticket_id = :tid");
        $stmt->execute(['tid' => $ticketId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = 'piece|' . (string)$r['label'] . '|' . (float)$r['prix_ht_snapshot'] . '|' . (float)$r['tva_snapshot'];
            $existingPiece[$k] = ['id' => (int)$r['id'], 'qty' => (int)$r['quantite']];
        }

        // 3) Appliquer les fusions: UPDATE des lignes existantes, INSERT pour les nouvelles
        // Prestations (MO)
        foreach ($pending['mo'] as $key => $e) {
            $label = $e['label'];
            $qty = (int)$e['qty'];
            $price = (float)$e['price'];
            $tva = (float)$e['tva'];

            if ($qty <= 0) {
                continue;
            }

            if (isset($existingMo[$key])) {
                // Incrémenter
                $upd = $this->pdo->prepare("UPDATE ticket_prestations SET quantite = quantite + :q WHERE id = :id");
                $upd->execute(['q' => $qty, 'id' => $existingMo[$key]['id']]);
            } else {
                // Insérer
                $ins = $this->pdo->prepare("INSERT INTO ticket_prestations (ticket_id, prestation_id, label, quantite, prix_ht_snapshot, tva_snapshot, is_custom, created_at) 
                    VALUES (:tid, NULL, :label, :q, :p, :tva, 0, CURRENT_TIMESTAMP)");
                $ins->execute([
                    'tid' => $ticketId,
                    'label' => $label,
                    'q' => $qty,
                    'p' => $price,
                    'tva' => $tva
                ]);
            }
        }

        // Consommables (Pièces)
        foreach ($pending['piece'] as $key => $e) {
            $label = $e['label']; // 'Pièce'
            $qty = (int)$e['qty'];
            $price = (float)$e['price'];
            $tva = (float)$e['tva'];

            if ($qty <= 0 && $price <= 0) {
                continue;
            }

            if (isset($existingPiece[$key])) {
                // Incrémenter (au moins la quantité)
                $upd = $this->pdo->prepare("UPDATE ticket_consommables SET quantite = quantite + :q WHERE id = :id");
                $upd->execute(['q' => max(1, $qty), 'id' => $existingPiece[$key]['id']]);
            } else {
                // Insérer (si qty=0 mais prix>0, on force qty=1)
                $insC = $this->pdo->prepare("INSERT INTO ticket_consommables (ticket_id, consommable_id, label, quantite, prix_ht_snapshot, tva_snapshot, is_custom, created_at)
                    VALUES (:tid, NULL, :label, :q, :p, :tva, 1, CURRENT_TIMESTAMP)");
                $insC->execute([
                    'tid' => $ticketId,
                    'label' => $label,
                    'q' => $qty > 0 ? $qty : 1,
                    'p' => $price,
                    'tva' => $tva
                ]);
            }
        }
    }
}
