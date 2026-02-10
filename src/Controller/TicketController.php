<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class TicketController
{
    private array $container;
    private $pdo;
    private $twig;
    private ?\App\Service\TicketService $ticketService = null;

    public function __construct(array $container = [])
    
    {
        $this->container = $container;
        $this->pdo = $container['pdo'] ?? null;
        $this->twig = $container['twig'] ?? null;
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $ticket = null;
        $client = null;
        $prestations = [];
        $consommables = [];

        if ($this->pdo) {
            if ($id > 0) {
                $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $id]);
                $ticket = $stmt->fetch();

                if ($ticket) {
                    $stmt = $this->pdo->prepare('SELECT * FROM ticket_prestations WHERE ticket_id = :id');
                    $stmt->execute(['id' => $id]);
                    $prestations = $stmt->fetchAll();

                    $stmt = $this->pdo->prepare('SELECT * FROM ticket_consommables WHERE ticket_id = :id');
                    $stmt->execute(['id' => $id]);
                    $consommables = $stmt->fetchAll();

                    $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
                    $stmt->execute(['id' => $ticket['client_id']]);
                    $client = $stmt->fetch();
                }
            } else {
                // Pré-sélection client via query param ?client_id=...
                $query = $request->getQueryParams();
                if (!empty($query['client_id'])) {
                    $cid = (int)$query['client_id'];
                    if ($cid > 0) {
                        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
                        $stmt->execute(['id' => $cid]);
                        $client = $stmt->fetch() ?: null;
                    }
                }
            }
        }

        $catalogue = [];
        $totaux = ['ht' => 0.0, 'tva' => 0.0, 'ttc' => 0.0];
        if ($this->pdo) {
            $svc = new \App\Service\TicketService($this->pdo);
            $catalogue = $svc->loadCatalogueGrouped();
            if ($ticket && !empty($ticket['id'])) {
                $totaux = $svc->computeTotals((int)$ticket['id']);
            }
        }

        // Recent documents for this client (devis/factures)
        $recentDocs = ['devis' => [], 'factures' => []];
        if ($this->pdo && $client) {
            $stmt = $this->pdo->prepare('SELECT id, numero, status, pdf_path, created_at FROM devis WHERE client_id = :cid ORDER BY created_at DESC LIMIT 5');
            $stmt->execute(['cid' => $client['id']]);
            $recentDocs['devis'] = $stmt->fetchAll() ?: [];

            $stmt = $this->pdo->prepare('SELECT id, numero, status, pdf_path, created_at FROM factures WHERE client_id = :cid ORDER BY created_at DESC LIMIT 5');
            $stmt->execute(['cid' => $client['id']]);
            $recentDocs['factures'] = $stmt->fetchAll() ?: [];
        }

        // Charger le planning existant pour ce ticket
        $ticketPlanning = null;
        if ($this->pdo && $ticket && !empty($ticket['id'])) {
            $planningService = new \App\Service\PlanningService($this->pdo);
            $ticketPlanning = $planningService->getByTicketId((int)$ticket['id']);
        }

        $response->getBody()->write($this->twig->render('tickets/edit.twig', [
            'ticket' => $ticket,
            'client' => $client,
            'prestations' => $prestations,
            'consommables' => $consommables,
            'catalogue' => $catalogue,
            'totaux' => $totaux,
            'recentDocs' => $recentDocs,
            'ticketPlanning' => $ticketPlanning
        ]));
        return $response;
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $wasCreatedNow = false;
        $data = (array)$request->getParsedBody();
        $bikeBrand = trim((string)($data['bike_brand'] ?? ''));
        $bikeModel = trim((string)($data['bike_model'] ?? ''));
        $bikeSerial = trim((string)($data['bike_serial'] ?? ''));
        $bikeNotes = trim((string)($data['bike_notes'] ?? ''));

        if (!$this->pdo) {
            $response->getBody()->write('Database not available');
            return $response->withStatus(500);
        }

        // création si nécessaire
        if ($id <= 0) {
            $client_id = (int)($data['client_id'] ?? 0);
            
            // Protection: ne créer un ticket que si on a au moins un client
            if ($client_id <= 0) {
                $response->getBody()->write('Client requis pour créer un ticket');
                return $response->withStatus(400);
            }
            
            // Créer le ticket même sans prestations (l'utilisateur pourra en ajouter plus tard)
            // received_at initialized to CURRENT_TIMESTAMP
            $stmt = $this->pdo->prepare('INSERT INTO tickets (client_id, status) VALUES (:client_id, :status)');
            $stmt->execute(['client_id' => $client_id, 'status' => 'open']);
            $id = (int)$this->pdo->lastInsertId();
            $wasCreatedNow = true;
            try {
                $this->pdo->prepare('UPDATE tickets SET received_at = CURRENT_TIMESTAMP WHERE id = :id')
                    ->execute(['id' => $id]);
            } catch (\Throwable $e) {
                // Colonne absente (ancienne base) : on ignore pour ne pas bloquer l'UX
            }

            // Stocker les infos vélo directement sur le ticket
            try {
                $updBike = $this->pdo->prepare('UPDATE tickets SET bike_brand = :bb, bike_model = :bm, bike_serial = :bs, bike_notes = :bn WHERE id = :tid');
                $updBike->execute([
                    'bb' => $bikeBrand,
                    'bm' => $bikeModel,
                    'bs' => $bikeSerial,
                    'bn' => $bikeNotes,
                    'tid' => $id
                ]);
            } catch (\PDOException $e) {
                // Ignorer si colonnes absentes
            }
        } else {
            try {
                $updBike = $this->pdo->prepare('UPDATE tickets SET bike_brand = :bb, bike_model = :bm, bike_serial = :bs, bike_notes = :bn WHERE id = :tid');
                $updBike->execute([
                    'bb' => $bikeBrand,
                    'bm' => $bikeModel,
                    'bs' => $bikeSerial,
                    'bn' => $bikeNotes,
                    'tid' => $id
                ]);
            } catch (\PDOException $e) {
                // Ignorer si colonnes absentes
            }
        }

        // Protection: si c'est un nouveau ticket sans prestations ni vélo, le supprimer pour éviter la pollution
        $hasNonEmptyArrayValue = static function ($value): bool {
            if (!is_array($value)) {
                return false;
            }
            foreach ($value as $v) {
                if (trim((string)$v) !== '') {
                    return true;
                }
            }
            return false;
        };

        $hasPrestations = $hasNonEmptyArrayValue($data['prest_id'] ?? null);
        $hasBikeInfo = !empty($bikeBrand) || !empty($bikeModel) || !empty($bikeSerial) || !empty($bikeNotes);
        $hasCustomPrestations = $hasNonEmptyArrayValue($data['custom_prest_label'] ?? null);
        $hasCustomPieces = $hasNonEmptyArrayValue($data['custom_piece_label'] ?? null);
        $hasCustomBikeSales = $hasNonEmptyArrayValue($data['custom_bike_label'] ?? null);
        $hasBikeSales = $hasCustomPieces || $hasCustomBikeSales;
        $hasTicketPayload = $hasPrestations || $hasCustomPrestations || $hasBikeSales;
        
        if ($id > 0 && !$hasPrestations && !$hasBikeInfo && !$hasCustomPrestations && !$hasBikeSales) {
            // Vérifier si le ticket a des lignes en base (prestations + consommables)
            $check = $this->pdo->prepare('
                SELECT
                    (SELECT COUNT(*) FROM ticket_prestations WHERE ticket_id = :id)
                  + (SELECT COUNT(*) FROM ticket_consommables WHERE ticket_id = :id)
                AS total_lines
            ');
            $check->execute(['id' => $id]);
            $prestCount = (int)($check->fetchColumn() ?? 0);
            if ($prestCount === 0) {
                // Ticket vide récemment créé : le supprimer
                $this->pdo->prepare('DELETE FROM tickets WHERE id = :id')->execute(['id' => $id]);
                
                // Rediriger avec un message d'erreur
                return $response->withHeader('Location', '/catalogue?error=empty_ticket_deleted')->withStatus(302);
            }
        }

        // Mettre à jour les prestations sélectionnées depuis l'UI tactile
        $this->ticketService = $this->ticketService ?? new \App\Service\TicketService($this->pdo);
        $this->ticketService->replacePrestationsFromPost($id, $data);
        $this->ticketService->computeTotals($id);

        // Garde-fou: éviter de conserver un ticket nouvellement créé avec payload inexploitable.
        if ($wasCreatedNow && $hasTicketPayload) {
            $check = $this->pdo->prepare('
                SELECT
                    (SELECT COUNT(*) FROM ticket_prestations WHERE ticket_id = :id)
                  + (SELECT COUNT(*) FROM ticket_consommables WHERE ticket_id = :id)
                AS total_lines
            ');
            $check->execute(['id' => $id]);
            $totalLines = (int)($check->fetchColumn() ?? 0);
            if ($totalLines === 0 && !$hasBikeInfo) {
                $this->pdo->prepare('DELETE FROM tickets WHERE id = :id')->execute(['id' => $id]);
                return $response->withHeader('Location', '/catalogue?error=invalid_ticket_payload')->withStatus(302);
            }
        }

        // Si on vient du constructeur de devis: rediriger vers l'aperçu ou le PDF
        $action = $data['_action'] ?? '';
        if ($action === 'devis' || $action === 'devis_pdf') {
            // Vérifier qu'un client est sélectionné
            $clientId = 0;
            if (!empty($data['client_id'])) {
                $clientId = (int)$data['client_id'];
            } else {
                // Récupérer le client lié au ticket si déjà défini
                $stmt = $this->pdo->prepare('SELECT client_id FROM tickets WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $row = $stmt->fetch();
                $clientId = (int)($row['client_id'] ?? 0);
            }
            if ($clientId <= 0) {
                return $response->withHeader('Location', '/catalogue?error=client_required')->withStatus(302);
            }
            if ($action === 'devis_pdf') {
                return $response->withHeader('Location', '/tickets/' . $id . '/devis/pdf')->withStatus(302);
            }
            return $response->withHeader('Location', '/tickets/' . $id . '/devis/preview')->withStatus(302);
        }

        // Si la requête provient de la fiche client (autostart), revenir au tableau de bord client
        $referer = $request->getHeaderLine('Referer') ?? '';
        if (!empty($referer)) {
            $parsed = parse_url($referer);
            $path = (string)($parsed['path'] ?? '');
            if ($path !== '' && strpos($path, '/clients/') !== false) {
                $queryParams = [];
                if (!empty($parsed['query'])) {
                    parse_str((string)$parsed['query'], $queryParams);
                }
                // Empêcher la boucle de recréation auto depuis la fiche client.
                unset($queryParams['autostart'], $queryParams['auto_create'], $queryParams['from']);
                $queryParams['created_ticket'] = (string)$id;
                $location = $path . '?' . http_build_query($queryParams);
                return $response->withHeader('Location', $location)->withStatus(302);
            }
        }
        // Comportement par défaut: rester sur la page du ticket
        return $response->withHeader('Location', '/tickets/' . $id . '/edit?success=1')->withStatus(302);
    }

    /**
     * Marquer un ticket comme "prêt" (prestation terminée)
     * POST /tickets/{id}/ready
     */
    public function markAsReady(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        if (!$this->pdo || $id <= 0) {
            $response->getBody()->write('Ticket introuvable');
            return $response->withStatus(404);
        }

        // Mettre à jour les prestations avant de changer le statut
        $data = (array)$request->getParsedBody();
        if (!empty($data)) {
            $this->ticketService = $this->ticketService ?? new \App\Service\TicketService($this->pdo);
            $this->ticketService->replacePrestationsFromPost($id, $data);
            $this->ticketService->computeTotals($id);
        }

        // Changer le statut à "ready"
        $stmt = $this->pdo->prepare('UPDATE tickets SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute(['status' => 'ready', 'id' => $id]);

        // Rediriger vers la file d'attente
        return $response->withHeader('Location', '/tickets/queue?status=ready')->withStatus(302);
    }

    private function gatherTicketData(int $id): array
    {
        if (!$this->pdo) {
            return [null, null, [], ['ht' => 0, 'tva' => 0, 'ttc' => 0]];
        }
        // Ticket
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $ticket = $stmt->fetch() ?: null;
        if (!$ticket) {
            return [null, null, [], ['ht' => 0, 'tva' => 0, 'ttc' => 0]];
        }
        // Client
        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $ticket['client_id']]);
        $client = $stmt->fetch() ?: null;

        // Prestations (lignes)
        $stmt = $this->pdo->prepare('SELECT label, quantite, prix_ht_snapshot, tva_snapshot FROM ticket_prestations WHERE ticket_id = :id');
        $stmt->execute(['id' => $id]);
        $lines = $stmt->fetchAll() ?: [];

        // Consommables (Pièces) — ajouter aux lignes pour PDF/aperçu
        $stmt = $this->pdo->prepare('SELECT label, quantite, prix_ht_snapshot, tva_snapshot FROM ticket_consommables WHERE ticket_id = :id');
        $stmt->execute(['id' => $id]);
        $cons = $stmt->fetchAll() ?: [];
        if (!empty($cons)) {
            $lines = array_merge($lines, $cons);
        }

        // Totaux
        $totaux = [
            'ht' => (float)($ticket['total_ht'] ?? 0),
            'tva' => 0.0,
            'ttc' => (float)($ticket['total_ttc'] ?? 0),
        ];
        if ($totaux['ht'] === 0 && $totaux['ttc'] === 0) {
            // fallback calcul local: TOTAL = somme HT, pas de TVA
            $ht = 0.0;
            foreach ($lines as $ln) {
                $q = (int)$ln['quantite'];
                $p = (float)$ln['prix_ht_snapshot'];
                $ht += $q * $p;
            }
            $totaux = ['ht' => $ht, 'tva' => 0.0, 'ttc' => $ht];
        }
        return [$ticket, $client, $lines, $totaux];
    }

    public function devisPreview(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        if (!$this->pdo || $id <= 0) {
            $response->getBody()->write('Ticket introuvable');
            return $response->withStatus(404);
        }
        // Intégrer les ajouts du formulaire (pending) avant de générer la facture
        $data = (array)$request->getParsedBody();
        if (!empty($data)) {
            $hasPostLines = false;
            foreach (['prest_id','qty','price_override','piece_qty','piece_price_override'] as $k) {
                if (!empty($data[$k])) { $hasPostLines = true; break; }
            }
            if ($hasPostLines) {
                $this->ticketService = $this->ticketService ?? new \App\Service\TicketService($this->pdo);
                $this->ticketService->replacePrestationsFromPost($id, $data);
                $this->ticketService->computeTotals($id);
            }
        }
        // Recharger les données après intégration des ajouts
        [$ticket, $client, $lines, $totaux] = $this->gatherTicketData($id);
        if (!$ticket || !$client) {
            $response->getBody()->write('Ticket ou client introuvable');
            return $response->withStatus(404);
        }

        $devis = [
            'numero' => 'PREVIEW-' . $id,
            'montant_ht' => $totaux['ht'],
            'montant_tva' => $totaux['tva'],
            'montant_ttc' => $totaux['ttc'],
            'status' => 'draft',
            'created_at' => date('Y-m-d'),
            'lines' => $lines
        ];

        $html = $this->twig->render('pdf/devis.twig', [
            'devis' => $devis,
            'client' => $client,
            'env' => $this->container['env'] ?? []
        ]);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withStatus(200);
    }

    public function devisPdf(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        if (!$this->pdo || $id <= 0) {
            $response->getBody()->write('Ticket introuvable');
            return $response->withStatus(404);
        }
        [$ticket, $client, $lines, $totaux] = $this->gatherTicketData($id);
        if (!$ticket || !$client) {
            $response->getBody()->write('Ticket ou client introuvable');
            return $response->withStatus(404);
        }

        $devis = [
            'numero' => 'DEV-' . $id,
            'montant_ht' => $totaux['ht'],
            'montant_tva' => $totaux['tva'],
            'montant_ttc' => $totaux['ttc'],
            'status' => 'draft',
            'created_at' => date('Y-m-d'),
            'lines' => $lines
        ];

        // Récupérer les informations de l'entreprise
        $companyService = new \App\Service\CompanyProfileService($this->pdo);
        $company = $companyService->getProfile();

        $pdf = ($this->container['get'])('pdf')->renderPdf('pdf/devis.twig', [
            'devis' => $devis,
            'client' => $client,
            'company' => $company,
            'env' => $this->container['env'] ?? []
        ], ['paper' => 'a4', 'orientation' => 'portrait']);

        $response->getBody()->write($pdf);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="devis-'.$id.'.pdf"')
            ->withStatus(200);
    }

    public function facturerConfirm(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $data = (array)$request->getParsedBody();
        if (!$this->pdo || $id <= 0) {
            $response->getBody()->write('Ticket introuvable');
            return $response->withStatus(404);
        }
        [$ticket, $client, $lines, $totaux] = $this->gatherTicketData($id);
        if (!$ticket || !$client) {
            $response->getBody()->write('Ticket ou client introuvable');
            return $response->withStatus(404);
        }

        // Numérotation facture
        $prefix = $_ENV['NUMSEQ_FACTURE_PREFIX'] ?? ($this->container['env']['NUMSEQ_FACTURE_PREFIX'] ?? '');
        $numService = new \App\Service\NumberingService($this->pdo, $prefix);
        $numero = $numService->next('facture', 4);

        // Créer la facture
        $stmt = $this->pdo->prepare('INSERT INTO factures (client_id, ticket_id, numero, montant_ht, montant_tva, montant_ttc, status, created_at) VALUES (:client_id, :ticket_id, :numero, :ht, :tva, :ttc, :status, CURRENT_TIMESTAMP)');
        $stmt->execute([
            'client_id' => $client['id'],
            'ticket_id' => $id,
            'numero' => $numero,
            'ht' => $totaux['ht'],
            'tva' => $totaux['tva'],
            'ttc' => $totaux['ttc'],
            'status' => 'unpaid'
        ]);
        $factureId = (int)$this->pdo->lastInsertId();

        // Enregistrer le règlement si un mode de paiement est fourni (CB / ESPECE)
        $payment = null;
        $methodRaw = strtoupper(trim((string)($data['payment_method'] ?? '')));
        if ($methodRaw === 'CB' || $methodRaw === 'ESPECE' || $methodRaw === 'ESPÈCE') {
            $method = ($methodRaw === 'CB') ? 'CB' : 'ESPECE';
            $stmtReg = $this->pdo->prepare('INSERT INTO reglements (facture_id, amount, method) VALUES (:fid, :amount, :method)');
            $stmtReg->execute(['fid' => $factureId, 'amount' => $totaux['ttc'], 'method' => $method]);
            // Marquer la facture comme payée
            $this->pdo->prepare('UPDATE factures SET status = :s WHERE id = :id')->execute(['s' => 'paid', 'id' => $factureId]);
            $payment = [
                'method' => $method,
                'amount' => $totaux['ttc'],
                'paid_at' => date('Y-m-d H:i')
            ];
        }

        // Récupérer les informations de l'entreprise
        $companyService = new \App\Service\CompanyProfileService($this->pdo);
        $company = $companyService->getProfile();

        // Générer PDF et enregistrer sous public/pdfs
        $webDir = '/pdfs/factures';
        $absDir = dirname(__DIR__, 2) . '/public' . $webDir;
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0755, true);
        }
        $relPath = $webDir . '/' . $numero . '.pdf';
        $absPath = $absDir . '/' . $numero . '.pdf';

        ($this->container['get'])('pdf')->savePdf('pdf/facture.twig', [
            'facture' => [
                'numero' => $numero,
                'montant_ht' => $totaux['ht'],
                'montant_tva' => $totaux['tva'],
                'montant_ttc' => $totaux['ttc'],
                'created_at' => date('Y-m-d'),
                'lines' => $lines
            ],
            'client' => $client,
            'company' => $company,
            'payment' => $payment,
            'env' => $this->container['env'] ?? []
        ], $absPath, ['paper' => 'a4', 'orientation' => 'portrait']);

        // Mettre à jour le chemin PDF
        $upd = $this->pdo->prepare('UPDATE factures SET pdf_path = :pdf WHERE id = :id');
        $upd->execute(['pdf' => $relPath, 'id' => $factureId]);

        // Marquer le ticket comme facturé
        $updT = $this->pdo->prepare('UPDATE tickets SET status = :s, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $updT->execute(['s' => 'invoiced', 'id' => $id]);

        // Envoi mail (simple) si email client présent
        $to = $client['email'] ?? '';
        if (!empty($to)) {
            $link = $relPath; // lien relatif servi par php -S
            $body = sprintf('<p>Bonjour %s,</p><p>Votre facture %s est disponible. Vous pouvez la télécharger ici : <a href="%s">%s</a>.</p><p>Cordialement,</p>', htmlspecialchars($client['name'] ?? '', ENT_QUOTES), htmlspecialchars($numero, ENT_QUOTES), htmlspecialchars($link, ENT_QUOTES), htmlspecialchars($link, ENT_QUOTES));
            ($this->container['get'])('mailer')->send($to, 'Votre facture ' . $numero, $body, $_ENV['COMPANY_EMAIL'] ?? null);
        }

        return $response->withHeader('Location', '/clients/' . $client['id'] . '?invoiced=1')->withStatus(302);
    }

    public function queue(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $response->getBody()->write('Database not available');
            return $response->withStatus(500);
        }

        // Récupérer le statut demandé (par défaut: open)
        $query = $request->getQueryParams();
        $status = $query['status'] ?? 'open';
        $activeTab = in_array($status, ['open', 'ready']) ? $status : 'open';

        // Vérifier si les colonnes existent
        $hasReceived = false;
        $hasBikeCols = false;
        try {
            $cols = $this->pdo->query("PRAGMA table_info(tickets)")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($cols as $col) {
                if (strtolower((string)($col['name'] ?? '')) === 'received_at') { $hasReceived = true; }
                if ($col['name'] === 'bike_brand') { $hasBikeCols = true; }
            }
        } catch (\Throwable $e) {}

        // Construire la requête SQL en fonction des colonnes disponibles
        $bikeSelect = $hasBikeCols ? 't.bike_brand, t.bike_model' : 'NULL as bike_brand, NULL as bike_model';
        
        // Récupérer les tickets ouverts
        $openTickets = [];
        if ($hasReceived) {
            $sql = "SELECT t.id, t.client_id, $bikeSelect, t.status,
                           t.created_at, t.received_at,
                           c.name AS client_name
                    FROM tickets t
                    LEFT JOIN clients c ON c.id = t.client_id
                    WHERE t.status = 'open'
                    ORDER BY COALESCE(t.received_at, t.created_at) ASC, t.id ASC";
        } else {
            $sql = "SELECT t.id, t.client_id, $bikeSelect, t.status,
                           t.created_at, NULL AS received_at,
                           c.name AS client_name
                    FROM tickets t
                    LEFT JOIN clients c ON c.id = t.client_id
                    WHERE t.status = 'open'
                    ORDER BY t.created_at ASC, t.id ASC";
        }
        $stmt = $this->pdo->query($sql);
        $openTickets = $stmt->fetchAll() ?: [];

        // Récupérer les tickets prêts
        $readyTickets = [];
        $sqlReady = "SELECT t.id, t.client_id, $bikeSelect, t.status,
                            t.created_at, t.updated_at, t.total_ttc,
                            c.name AS client_name
                     FROM tickets t
                     LEFT JOIN clients c ON c.id = t.client_id
                     WHERE t.status = 'ready'
                     ORDER BY t.updated_at ASC, t.id ASC";
        $stmt = $this->pdo->query($sqlReady);
        $readyTickets = $stmt->fetchAll() ?: [];

        $html = $this->twig->render('tickets/queue.twig', [
            'openTickets' => $openTickets,
            'readyTickets' => $readyTickets,
            'activeTab' => $activeTab,
            'env' => $this->container['env'] ?? []
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Auto-save prestations via AJAX (sans redirection)
     * POST /tickets/{id}/prestations/auto-save
     */
    public function autoSave(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $data = (array)$request->getParsedBody();

        if (!$this->pdo || $id <= 0) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Invalid ticket ID']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // Utiliser le service TicketService pour remplacer les prestations
            $this->ticketService = $this->ticketService ?? new \App\Service\TicketService($this->pdo);
            $this->ticketService->replacePrestationsFromPost($id, $data);
            $this->ticketService->computeTotals($id);

            $response->getBody()->write(json_encode(['success' => true, 'message' => 'Saved']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Supprimer une prestation ou consommable d'un ticket
     * POST /tickets/{id}/prestations/{prest_id}/delete
     */
    public function deletePrestation(Request $request, Response $response, array $args): Response
    {
        $ticketId = (int)($args['id'] ?? 0);
        $prestId = (int)($args['prest_id'] ?? 0);

        if (!$this->pdo || $ticketId <= 0 || $prestId <= 0) {
            $response->getBody()->write('Paramètre invalide');
            return $response->withStatus(400);
        }

        try {
            // Essayer de supprimer de ticket_prestations d'abord
            $stmt = $this->pdo->prepare('DELETE FROM ticket_prestations WHERE ticket_id = :ticket_id AND id = :prest_id');
            $stmt->execute(['ticket_id' => $ticketId, 'prest_id' => $prestId]);
            $prestDeleted = $stmt->rowCount();

            // Si rien n'a été supprimé, essayer ticket_consommables (Pièces/Ventes vélo)
            if ($prestDeleted === 0) {
                $stmt = $this->pdo->prepare('DELETE FROM ticket_consommables WHERE ticket_id = :ticket_id AND id = :prest_id');
                $stmt->execute(['ticket_id' => $ticketId, 'prest_id' => $prestId]);
            }

            // Recalculer les totaux du ticket
            $svc = new \App\Service\TicketService($this->pdo);
            $svc->computeTotals($ticketId);

        } catch (\Throwable $e) {
            return $response->withStatus(500)->withContent('Erreur lors de la suppression');
        }

        // Redirection vers la page d'édition du ticket
        return $response->withHeader('Location', '/tickets/' . $ticketId . '/edit?deleted=1')->withStatus(302);
    }

    /**
     * Planifier la récupération d'un ticket
     * POST /tickets/{id}/plan
     */
    public function plan(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        if (!$this->pdo || $id <= 0) {
            $response->getBody()->write('Ticket introuvable');
            return $response->withStatus(404);
        }

        try {
            $data = (array)$request->getParsedBody();
            $recoveryDate = $data['recovery_date'] ?? '';
            
            if (empty($recoveryDate)) {
                throw new \Exception('La date de récupération est obligatoire.');
            }

            // Créer le planning via le service
            $planningService = new \App\Service\PlanningService($this->pdo);
            $planningService->createFromTicket($id, [
                'recovery_date' => $recoveryDate,
                'notes' => $data['notes'] ?? null
            ]);

            return $response
                ->withHeader('Location', "/tickets/{$id}/edit?planned=1")
                ->withStatus(302);

        } catch (\Exception $e) {
            return $response
                ->withHeader('Location', "/tickets/{$id}/edit?error=" . urlencode($e->getMessage()))
                ->withStatus(302);
        }
    }

    /**
     * Supprimer le planning d'un ticket
     * POST /tickets/{id}/unplan
     */
    public function unplan(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        if (!$this->pdo || $id <= 0) {
            $response->getBody()->write('Ticket introuvable');
            return $response->withStatus(404);
        }

        try {
            // Supprimer le planning via le service
            $planningService = new \App\Service\PlanningService($this->pdo);
            $planningService->deleteByTicketId($id);

            return $response
                ->withHeader('Location', "/tickets/{$id}/edit?unplanned=1")
                ->withStatus(302);

        } catch (\Exception $e) {
            return $response
                ->withHeader('Location', "/tickets/{$id}/edit?error=" . urlencode($e->getMessage()))
                ->withStatus(302);
        }
    }
}
