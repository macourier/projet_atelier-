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

        $response->getBody()->write($this->twig->render('tickets/edit.twig', [
            'ticket' => $ticket,
            'client' => $client,
            'prestations' => $prestations,
            'consommables' => $consommables,
            'catalogue' => $catalogue,
            'totaux' => $totaux,
            'recentDocs' => $recentDocs
        ]));
        return $response;
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
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
            // received_at initialized to CURRENT_TIMESTAMP
            $stmt = $this->pdo->prepare('INSERT INTO tickets (client_id, status) VALUES (:client_id, :status)');
            $stmt->execute(['client_id' => $client_id, 'status' => 'open']);
            $id = (int)$this->pdo->lastInsertId();
            try {
                $this->pdo->prepare('UPDATE tickets SET received_at = CURRENT_TIMESTAMP WHERE id = :id')
                    ->execute(['id' => $id]);
            } catch (\Throwable $e) {
                // Colonne absente (ancienne base) : on ignore pour ne pas bloquer l'UX
            }

            // Stocker les infos vélo directement sur le ticket
            $updBike = $this->pdo->prepare('UPDATE tickets SET bike_brand = :bb, bike_model = :bm, bike_serial = :bs, bike_notes = :bn WHERE id = :tid');
            $updBike->execute([
                'bb' => $bikeBrand,
                'bm' => $bikeModel,
                'bs' => $bikeSerial,
                'bn' => $bikeNotes,
                'tid' => $id
            ]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE tickets SET bike_brand = :bb, bike_model = :bm, bike_serial = :bs, bike_notes = :bn, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute([
                'bb' => $bikeBrand,
                'bm' => $bikeModel,
                'bs' => $bikeSerial,
                'bn' => $bikeNotes,
                'id' => $id
            ]);
        }

        // Mettre à jour les prestations sélectionnées depuis l'UI tactile
        $this->ticketService = $this->ticketService ?? new \App\Service\TicketService($this->pdo);
        $this->ticketService->replacePrestationsFromPost($id, $data);
        $this->ticketService->computeTotals($id);

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
        if (!empty($referer) && strpos($referer, '/clients/') !== false) {
            $sep = (strpos($referer, '?') === false) ? '?' : '&';
            return $response->withHeader('Location', $referer . $sep . 'created_ticket=' . $id)->withStatus(302);
        }
        // Comportement par défaut: rester sur la page du ticket
        return $response->withHeader('Location', '/tickets/' . $id . '/edit?success=1')->withStatus(302);
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

        $pdf = ($this->container['get'])('pdf')->renderPdf('pdf/devis.twig', [
            'devis' => $devis,
            'client' => $client,
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
        // Tickets ouverts, tri du plus ancien au plus récent — compatibilité si la colonne received_at est absente
        $hasReceived = false;
        try {
            $cols = $this->pdo->query("PRAGMA table_info(tickets)")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($cols as $col) {
                if (strtolower((string)($col['name'] ?? '')) === 'received_at') { $hasReceived = true; break; }
            }
        } catch (\Throwable $e) {}

        if ($hasReceived) {
            $sql = "SELECT t.id, t.client_id, t.bike_brand, t.bike_model, t.status,
                           t.created_at, t.received_at,
                           c.name AS client_name
                    FROM tickets t
                    LEFT JOIN clients c ON c.id = t.client_id
                    WHERE t.status = 'open'
                    ORDER BY COALESCE(t.received_at, t.created_at) ASC, t.id ASC";
        } else {
            $sql = "SELECT t.id, t.client_id, t.bike_brand, t.bike_model, t.status,
                           t.created_at, NULL AS received_at,
                           c.name AS client_name
                    FROM tickets t
                    LEFT JOIN clients c ON c.id = t.client_id
                    WHERE t.status = 'open'
                    ORDER BY t.created_at ASC, t.id ASC";
        }
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll() ?: [];

        $html = $this->twig->render('tickets/queue.twig', [
            'tickets' => $rows,
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
}
