<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class DevisController
{
    private array $container;
    private $pdo;
    private $twig;

    public function __construct(array $container = [])
    {
        $this->container = $container;
        $this->pdo = $container['pdo'] ?? null;
        $this->twig = $container['twig'] ?? null;
    }

    // Constructeur de devis (remplace le flux "nouveau ticket")
    public function builder(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $client = null;

        if ($this->pdo && !empty($query['client_id'])) {
            $cid = (int)$query['client_id'];
            if ($cid > 0) {
                $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $cid]);
                $client = $stmt->fetch() ?: null;
            }
        }

        // Catalogue groupé (même service que tickets)
        $catalogue = [];
        if ($this->pdo) {
            $svc = new \App\Service\TicketService($this->pdo);
            $catalogue = $svc->loadCatalogueGrouped();
        }

        $error = isset($query['error']) ? (string)$query['error'] : null;
        $totaux = ['ht' => 0.0, 'tva' => 0.0, 'ttc' => 0.0];

        $response->getBody()->write($this->twig->render('devis/builder.twig', [
            'client' => $client,
            'catalogue' => $catalogue,
            'totaux' => $totaux,
            'error' => $error,
        ]));
        return $response;
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $devis = null;
        $client = null;

        if ($this->pdo && $id > 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM devis WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $devis = $stmt->fetch();

            if ($devis) {
                $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $devis['client_id']]);
                $client = $stmt->fetch();
            }
        }

        if (!$devis) {
            $response->getBody()->write($this->twig->render('devis/show.twig', ['devis' => null, 'error' => 'Devis non trouvé.']));
            return $response;
        }

        $response->getBody()->write($this->twig->render('devis/show.twig', [
            'devis' => $devis,
            'client' => $client
        ]));
        return $response;
    }

    // Génération PDF directe depuis /catalogue sans persistance (pas de ticket, pas d'écriture DB)
    public function directPdf(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();

        // Champs optionnels saisis dans le modal
        $clientName = trim((string)($data['name'] ?? ''));
        $clientPhone = trim((string)($data['phone'] ?? ''));
        $brand = trim((string)($data['brand'] ?? ''));

        // Lignes sélectionnées depuis le catalogue
        $prestIds = is_array($data['prest_id'] ?? null) ? $data['prest_id'] : [];
        $qtys = is_array($data['qty'] ?? null) ? $data['qty'] : [];
        $priceOverrides = is_array($data['price_override'] ?? null) ? $data['price_override'] : [];
        $pieceQtys = is_array($data['piece_qty'] ?? null) ? $data['piece_qty'] : [];
        $piecePriceOverrides = is_array($data['piece_price_override'] ?? null) ? $data['piece_price_override'] : [];

        $lines = [];
        $totalHt = 0.0;

        foreach ($prestIds as $idx => $pidRaw) {
            $pid = trim((string)$pidRaw);
            if ($pid === '') {
                continue;
            }
            $qty = (int)($qtys[$idx] ?? 1);
            if ($qty <= 0) { $qty = 1; }

            // Charger la ligne catalogue si possible
            $label = 'Prestation #' . $pid;
            $basePrice = 0.0;
            $tva = 0.0;

            if ($this->pdo) {
                $sel = $this->pdo->prepare("SELECT libelle, COALESCE(prix_main_oeuvre_ht,0) AS prix, COALESCE(piece_libelle,'Pièce') AS piece_libelle, COALESCE(piece_prix_ht,0) AS piece_prix_ht, COALESCE(tva_pct,0) AS tva FROM prestations_catalogue WHERE id = :id AND deleted_at IS NULL LIMIT 1");
                $sel->execute(['id' => $pid]);
                $row = $sel->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $label = (string)($row['libelle'] ?? $label);
                    $basePrice = (float)($row['prix'] ?? 0);
                    $tva = (float)($row['tva'] ?? 0);
                }
            }

            // Prix appliqué (override prioritaire)
            $price = isset($priceOverrides[$idx]) && $priceOverrides[$idx] !== '' ? (float)$priceOverrides[$idx] : $basePrice;

            $lines[] = [
                'label' => $label,
                'quantite' => $qty,
                'prix_ht_snapshot' => $price,
                'tva_snapshot' => $tva,
            ];
            $totalHt += ($qty * $price);

            // Ligne "Pièce" associée (optionnelle)
            $pqty = isset($pieceQtys[$idx]) ? (int)$pieceQtys[$idx] : 0;
            $pieceBase = isset($row['piece_prix_ht']) ? (float)$row['piece_prix_ht'] : 0.0;
            $pprice = (isset($piecePriceOverrides[$idx]) && $piecePriceOverrides[$idx] !== '') ? (float)$piecePriceOverrides[$idx] : $pieceBase;
            if ($pqty > 0 || $pprice > 0) {
                if ($pqty <= 0) { $pqty = 1; }
                $lines[] = [
                    'label' => (string)($row['piece_libelle'] ?? 'Pièce'),
                    'quantite' => $pqty,
                    'prix_ht_snapshot' => $pprice,
                    'tva_snapshot' => $tva,
                ];
                $totalHt += ($pqty * $pprice);
            }
        }

        // Lignes personnalisées (depuis la tuile du Catalogue)
        $cPrestLabels = is_array($data['custom_prest_label'] ?? null) ? $data['custom_prest_label'] : [];
        $cPrestPrices = is_array($data['custom_prest_price'] ?? null) ? $data['custom_prest_price'] : [];
        $maxp = max(count($cPrestLabels), count($cPrestPrices));
        for ($i = 0; $i < $maxp; $i++) {
            $lab = isset($cPrestLabels[$i]) ? trim((string)$cPrestLabels[$i]) : '';
            $prw = isset($cPrestPrices[$i]) ? (string)$cPrestPrices[$i] : '';
            $prw = str_replace(',', '.', trim($prw));
            $val = is_numeric($prw) ? (float)$prw : 0.0;
            if ($lab === '' || $val < 0) { continue; }
            $lines[] = ['label' => $lab, 'quantite' => 1, 'prix_ht_snapshot' => $val, 'tva_snapshot' => 0.0];
            $totalHt += $val;
        }
        $cPieceLabels = is_array($data['custom_piece_label'] ?? null) ? $data['custom_piece_label'] : [];
        $cPiecePrices = is_array($data['custom_piece_price'] ?? null) ? $data['custom_piece_price'] : [];
        $maxc = max(count($cPieceLabels), count($cPiecePrices));
        for ($i = 0; $i < $maxc; $i++) {
            $lab = isset($cPieceLabels[$i]) ? trim((string)$cPieceLabels[$i]) : '';
            $prw = isset($cPiecePrices[$i]) ? (string)$cPiecePrices[$i] : '';
            $prw = str_replace(',', '.', trim($prw));
            $val = is_numeric($prw) ? (float)$prw : 0.0;
            if ($lab === '' || $val < 0) { continue; }
            $lines[] = ['label' => $lab, 'quantite' => 1, 'prix_ht_snapshot' => $val, 'tva_snapshot' => 0.0];
            $totalHt += $val;
        }

        // Construire l'objet devis éphémère
        $numero = 'DIRECT-' . date('Ymd-His');
        $devis = [
            'numero' => $numero,
            'created_at' => date('Y-m-d'),
            'lines' => $lines,
            'montant_ht' => $totalHt,
            'montant_tva' => 0.0,
            'montant_ttc' => $totalHt, // TTC=HT (pas de TVA)
        ];

        // Client éphémère (affichage conditionnel dans le template)
        $client = [
            'name' => $clientName !== '' ? $clientName : null,
            'address' => null,
            'email' => null,
            'phone' => $clientPhone !== '' ? $clientPhone : null,
        ];
        $meta = [
            'brand' => $brand !== '' ? $brand : null,
        ];

        // Récupérer les informations de l'entreprise
        $companyService = new \App\Service\CompanyProfileService($this->pdo);
        $company = $companyService->getProfile();

        // Rendu PDF inline (nouvel onglet côté client via target="_blank")
        $pdf = ($this->container['get'])('pdf')->renderPdf('pdf/devis.twig', [
            'devis' => $devis,
            'client' => $client,
            'company' => $company,
            'meta' => $meta,
            'env' => $this->container['env'] ?? []
        ], ['paper' => 'a4', 'orientation' => 'portrait']);

        // Nettoyer d'éventuels buffers pour éviter tout octet parasite avant le PDF
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
        }

        // Poser les en-têtes avant d'écrire le binaire
        $response = $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="devis-'.$numero.'.pdf"')
            ->withHeader('Content-Length', (string)strlen($pdf))
            ->withStatus(200);

        $response->getBody()->write($pdf);
        return $response;
    }
}
