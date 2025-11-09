<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class ClientController
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

    public function index(Request $request, Response $response): Response
    {
        $clients = [];
        if ($this->pdo) {
            $stmt = $this->pdo->query('SELECT * FROM clients ORDER BY name');
            $clients = $stmt->fetchAll();
        }
        $response->getBody()->write($this->twig->render('clients/index.twig', ['clients' => $clients]));
        return $response;
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $client = null;
        $velos = [];
        $tickets = [];
        if ($this->pdo && $id > 0) {
            // Client
            $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $client = $stmt->fetch();

            if ($client) {

                // Tickets du client (infos vélo désormais stockées sur le ticket)
                $stmt = $this->pdo->prepare('SELECT t.* FROM tickets t WHERE t.client_id = :cid ORDER BY t.created_at DESC');
                $stmt->execute(['cid' => $id]);
                $tickets = $stmt->fetchAll();
            }
        }

        if (!$client) {
            $response->getBody()->write($this->twig->render('clients/show.twig', ['client' => null, 'error' => 'Client non trouvé.']));
            return $response;
        }

        // KPIs Vue 360°
        $kpis = [
            'velos' => is_array($velos) ? count($velos) : 0,
            'tickets_open' => 0,
            'tickets_closed' => 0,
            'devis' => 0,
            'factures_unpaid' => 0,
            'impayes_ttc' => 0.0,
            'last_activity' => null
        ];
        if ($this->pdo) {
            // tickets open
            $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM tickets WHERE client_id = :cid AND status = :s');
            $stmt->execute(['cid' => $id, 's' => 'open']);
            $row = $stmt->fetch();
            $kpis['tickets_open'] = (int)($row['c'] ?? 0);

            // tickets closed (tout sauf open)
            $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM tickets WHERE client_id = :cid AND status != :s');
            $stmt->execute(['cid' => $id, 's' => 'open']);
            $row = $stmt->fetch();
            $kpis['tickets_closed'] = (int)($row['c'] ?? 0);

            // devis count
            $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM devis WHERE client_id = :cid');
            $stmt->execute(['cid' => $id]);
            $row = $stmt->fetch();
            $kpis['devis'] = (int)($row['c'] ?? 0);

            // factures impayées + total impayé
            $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c, COALESCE(SUM(montant_ttc),0) AS sumttc FROM factures WHERE client_id = :cid AND status = :s');
            $stmt->execute(['cid' => $id, 's' => 'unpaid']);
            $row = $stmt->fetch();
            $kpis['factures_unpaid'] = (int)($row['c'] ?? 0);
            $kpis['impayes_ttc'] = (float)($row['sumttc'] ?? 0.0);

            // dernière activité (max created_at parmi tickets/devis/factures)
            $dates = [];
            $q = $this->pdo->prepare('SELECT MAX(created_at) AS m FROM tickets WHERE client_id = :cid');
            $q->execute(['cid' => $id]);
            $r = $q->fetch(); if (!empty($r['m'])) $dates[] = $r['m'];

            $q = $this->pdo->prepare('SELECT MAX(created_at) AS m FROM devis WHERE client_id = :cid');
            $q->execute(['cid' => $id]);
            $r = $q->fetch(); if (!empty($r['m'])) $dates[] = $r['m'];

            $q = $this->pdo->prepare('SELECT MAX(created_at) AS m FROM factures WHERE client_id = :cid');
            $q->execute(['cid' => $id]);
            $r = $q->fetch(); if (!empty($r['m'])) $dates[] = $r['m'];

            if (!empty($dates)) {
                rsort($dates);
                $kpis['last_activity'] = $dates[0];
            }
        }

        // Factures par ticket (dernière facture par ticket)
        $facturesByTicket = [];
        if ($this->pdo) {
            $fs = $this->pdo->prepare('SELECT id, ticket_id, numero, status, pdf_path FROM factures WHERE client_id = :cid ORDER BY created_at DESC');
            $fs->execute(['cid' => $id]);
            $rowsF = $fs->fetchAll() ?: [];
            foreach ($rowsF as $f) {
                $tk = (int)($f['ticket_id'] ?? 0);
                if ($tk > 0 && !isset($facturesByTicket[$tk])) {
                    $facturesByTicket[$tk] = $f;
                }
            }
        }

        // Récupérer le dernier vélo (marque/modèle) non vide pour ce client (fallback d'affichage)
        $lastBikeBrand = '';
        $lastBikeModel = '';
        if ($this->pdo && $id > 0) {
            try {
                $st = $this->pdo->prepare("SELECT bike_brand, bike_model FROM tickets WHERE client_id = :cid AND (bike_model IS NOT NULL AND TRIM(bike_model) <> '') ORDER BY created_at DESC LIMIT 1");
                $st->execute(['cid' => $id]);
                $lb = $st->fetch();
                if ($lb) {
                    $lastBikeBrand = (string)($lb['bike_brand'] ?? '');
                    $lastBikeModel = (string)($lb['bike_model'] ?? '');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $response->getBody()->write($this->twig->render('clients/show.twig', [
            'client' => $client,
            'velos' => $velos,
            'tickets' => $tickets,
            'kpis' => $kpis,
            'facturesByTicket' => $facturesByTicket,
            'last_bike' => ['brand' => $lastBikeBrand, 'model' => $lastBikeModel]
        ]));
        return $response;
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $client = null;
        if ($this->pdo && $id > 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $client = $stmt->fetch();
        }
        $query = $request->getQueryParams();
        $return = isset($query['return']) ? (string)$query['return'] : null;

        // Pré-remplissage lors de la création depuis /devis/new
        if (!$client && $id === 0) {
            $client = [
                'name' => trim($query['name'] ?? ''),
                'address' => trim($query['address'] ?? ''),
                'email' => trim($query['email'] ?? ''),
                'phone' => trim($query['phone'] ?? '')
            ];
        }

        // Pré-remplir le modèle (et marque) depuis le dernier ticket si disponible
        if ($this->pdo && $id > 0) {
            try {
                $st = $this->pdo->prepare('SELECT bike_brand, bike_model FROM tickets WHERE client_id = :cid ORDER BY created_at DESC LIMIT 1');
                $st->execute(['cid' => $id]);
                $last = $st->fetch();
                if ($last) {
                    $bm = trim((string)($last['bike_model'] ?? ''));
                    $bb = trim((string)($last['bike_brand'] ?? ''));
                    if (($client['bike_model'] ?? '') === '' && $bm !== '') {
                        $client['bike_model'] = $bm;
                    }
                    if (($client['bike_brand'] ?? '') === '' && $bb !== '') {
                        $client['bike_brand'] = $bb;
                    }
                }
            } catch (\Throwable $e) {
                // ignore, pré-remplissage facultatif
            }
        }

        $response->getBody()->write($this->twig->render('clients/edit.twig', [
            'client' => $client,
            'return' => $return,
            'auto_create' => !empty($query['auto_create'])
        ]));
        return $response;
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $data = (array)$request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $address = trim($data['address'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $note = trim($data['note'] ?? '');

        if (!$this->pdo) {
            $response->getBody()->write('Database not available');
            return $response->withStatus(500);
        }

        if ($id > 0) {
            // update
            $stmt = $this->pdo->prepare('UPDATE clients SET name = :name, address = :address, email = :email, phone = :phone, note = :note, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute([
                'name' => $name,
                'address' => $address,
                'email' => $email,
                'phone' => $phone,
                'note' => $note,
                'id' => $id
            ]);
        } else {
            // create
            $stmt = $this->pdo->prepare('INSERT INTO clients (name, address, email, phone, note) VALUES (:name, :address, :email, :phone, :note)');
            $stmt->execute([
                'name' => $name,
                'address' => $address,
                'email' => $email,
                'phone' => $phone,
                'note' => $note
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }

        // Propager le modèle de vélo saisi côté client vers les tickets du client où le modèle est vide
        $bikeModel = trim($data['bike_model'] ?? '');
        if ($bikeModel !== '' && $id > 0) {
            try {
                $stmt = $this->pdo->prepare("UPDATE tickets SET bike_model = :bm WHERE client_id = :cid AND (bike_model IS NULL OR TRIM(bike_model) = '')");
                $stmt->execute(['bm' => $bikeModel, 'cid' => $id]);
            } catch (\Throwable $e) {
                // ne pas bloquer la sauvegarde client si la propagation échoue
            }
        }

        // Redirection: si "return" fourni (ex: /devis/new), y retourner avec client_id
        $ret = trim($data['return'] ?? '');
        // Fallback: si non fourni dans le POST, essayer de le récupérer depuis le Referer (?return=...)
        if ($ret === '') {
            $referer = $request->getHeaderLine('Referer') ?? '';
            if ($referer !== '') {
                $qs = parse_url($referer, PHP_URL_QUERY);
                if ($qs) {
                    parse_str($qs, $refParams);
                    if (!empty($refParams['return'])) {
                        $ret = (string)$refParams['return'];
                    }
                }
            }
        }
        // Déterminer auto_create / autostart depuis POST ou Referer
        $autoCreate = 0; $autostart = 0;
        if (!empty($data['auto_create'])) { $autoCreate = 1; }
        if (!empty($data['autostart'])) { $autostart = 1; }
        if (empty($data['auto_create']) || empty($data['autostart'])) {
            $qs2 = !empty($referer) ? parse_url($referer, PHP_URL_QUERY) : null;
            if ($qs2) {
                parse_str($qs2, $refParams2);
                if (!$autoCreate && !empty($refParams2['auto_create'])) { $autoCreate = 1; }
                if (!$autostart && !empty($refParams2['autostart'])) { $autostart = 1; }
            }
        }

        if ($ret !== '') {
            // Si retour depuis le builder de devis, aller au tableau de bord client
            if (preg_match('#^/(devis/new|catalogue)#', $ret) === 1) {
                // Forcer l'autostart pour créer automatiquement un ticket depuis le brouillon
                $params = '?from=devis&autostart=1' . ($autoCreate ? '&auto_create=1' : '');
                // Passer le modèle saisi pour le récupérer côté clients/show (création ticket)
                if (!empty($bikeModel)) {
                    $params .= '&bike_model=' . rawurlencode($bikeModel);
                }
                $redir = '/clients/' . $id . $params;
                return $response->withHeader('Location', $redir)->withStatus(302);
            }
            // Sanitize basique: éviter URLs externes
            if (strpos($ret, 'http://') === 0 || strpos($ret, 'https://') === 0) {
                $ret = '/';
            }
            $sep = (strpos($ret, '?') === false) ? '?' : '&';
            return $response->withHeader('Location', $ret . $sep . 'client_id=' . $id)->withStatus(302);
        }

        return $response->withHeader('Location', '/clients/'.$id)->withStatus(302);
    }
}
