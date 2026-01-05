<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Service\CompanyProfileService;

class AdminCompanySettingsController
{
    private array $container;
    private $twig;
    private ?CompanyProfileService $companyProfileService;

    public function __construct(array $container = [])
    {
        $this->container = $container;
        $this->twig = $container['twig'] ?? null;
        $pdo = $container['pdo'] ?? null;
        if ($pdo) {
            $this->companyProfileService = new CompanyProfileService($pdo);
        }
    }

    public function index(Request $request, Response $response): Response
    {
        if (!$this->companyProfileService || !$this->twig) {
            return $response->withStatus(500);
        }

        // Récupérer le profil actuel
        $profile = $this->companyProfileService->getProfile();

        // Récupérer et supprimer les messages flash
        $flashSuccess = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : null;
        $flashError = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : null;
        if ($flashSuccess !== null) {
            unset($_SESSION['flash_success']);
        }
        if ($flashError !== null) {
            unset($_SESSION['flash_error']);
        }

        $html = $this->twig->render('admin/company-settings.twig', [
            'profile' => $profile,
            'flash_success' => $flashSuccess,
            'flash_error' => $flashError
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function uploadLogoTemp(Request $request, Response $response): Response
    {
        if (!$this->companyProfileService) {
            $response->getBody()->write(json_encode(['error' => 'Service non disponible']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        try {
            $uploadedFiles = $request->getUploadedFiles();

            if (!isset($uploadedFiles['logo']) || $uploadedFiles['logo']->getError() !== UPLOAD_ERR_OK) {
                $response->getBody()->write(json_encode(['error' => 'Aucun fichier ou erreur d\'upload']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $logoFile = $uploadedFiles['logo'];

            // Validation du type MIME
            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
            $clientMediaType = $logoFile->getClientMediaType();
            if (!in_array($clientMediaType, $allowedTypes)) {
                $response->getBody()->write(json_encode(['error' => 'Type de fichier non autorisé. Seuls PNG et JPEG sont acceptés.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Validation de la taille (max 2 Mo)
            $maxSize = 2 * 1024 * 1024;
            if ($logoFile->getSize() > $maxSize) {
                $response->getBody()->write(json_encode(['error' => 'Le fichier est trop volumineux. Maximum 2 Mo.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Générer un nom de fichier unique
            $extension = pathinfo($logoFile->getClientFilename(), PATHINFO_EXTENSION);
            $filename = 'temp_' . uniqid() . '.' . $extension;

            // Chemin de destination
            $uploadDir = __DIR__ . '/../../public/uploads/temp';
            $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            // Déplacer le fichier
            $logoFile->moveTo($destination);

            // Retourner le chemin relatif
            $logoPath = '/uploads/temp/' . $filename;
            $response->getBody()->write(json_encode(['logo_path' => $logoPath]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function update(Request $request, Response $response): Response
    {
        if (!$this->companyProfileService) {
            return $response->withStatus(500);
        }

        try {
            // Récupérer les données du formulaire
            $parsedBody = $request->getParsedBody();
            $uploadedFiles = $request->getUploadedFiles();

            // Préparer les données du profil
            $data = [
                'name' => trim($parsedBody['name'] ?? ''),
                'address_line1' => trim($parsedBody['address_line1'] ?? ''),
                'address_line2' => trim($parsedBody['address_line2'] ?? ''),
                'postcode' => trim($parsedBody['postcode'] ?? ''),
                'city' => trim($parsedBody['city'] ?? ''),
                'phone' => trim($parsedBody['phone'] ?? ''),
                'email' => trim($parsedBody['email'] ?? '')
            ];

            // Validation basique
            if (empty($data['name'])) {
                throw new \Exception('Le nom de l\'entreprise est obligatoire.');
            }
            if (empty($data['address_line1'])) {
                throw new \Exception('L\'adresse est obligatoire.');
            }
            if (empty($data['postcode'])) {
                throw new \Exception('Le code postal est obligatoire.');
            }
            if (empty($data['city'])) {
                throw new \Exception('La ville est obligatoire.');
            }

            // Gérer l'upload du logo si un fichier est fourni
            if (isset($uploadedFiles['logo']) && $uploadedFiles['logo']->getError() === UPLOAD_ERR_OK) {
                $logoFile = $uploadedFiles['logo'];
                
                // Convertir en tableau pour le service
                $fileArray = [
                    'name' => $logoFile->getClientFilename(),
                    'type' => $logoFile->getClientMediaType(),
                    'size' => $logoFile->getSize(),
                    'tmp_name' => $logoFile->getStream()->getMetadata('uri')
                ];

                // Upload du logo
                $logoPath = $this->companyProfileService->uploadLogo($fileArray);
                if ($logoPath) {
                    $this->companyProfileService->updateLogo($logoPath);
                }
            }

            // Mettre à jour le profil
            $result = $this->companyProfileService->updateProfile($data);

            if ($result) {
                $_SESSION['flash_success'] = 'Paramètres mis à jour avec succès.';
            } else {
                throw new \Exception('Erreur lors de la mise à jour du profil.');
            }

        } catch (\Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        // Rediriger vers la page de configuration
        return $response
            ->withHeader('Location', '/admin/company-settings')
            ->withStatus(302);
    }

    /**
     * Génère un aperçu PDF de facture avec les données mockées
     */
    public function previewInvoice(Request $request, Response $response): Response
    {
        if (!$this->companyProfileService || !$this->twig) {
            return $response->withStatus(500);
        }

        try {
            // Données mockées pour l'aperçu
            $profile = $this->companyProfileService->getProfile();
            
            // Vérifier si un logo temporaire est fourni
            $queryParams = $request->getQueryParams();
            if (isset($queryParams['logo_temp'])) {
                $profile['logo_path'] = $queryParams['logo_temp'];
            }
            
            $mockData = [
                'company' => $profile,
                'client' => [
                    'name' => 'Client test',
                    'address' => '1 rue de l\'examen',
                    'phone' => '06 00 00 00 00',
                    'email' => 'client.test@example.com'
                ],
                'facture' => [
                    'numero' => 'PREVIEW',
                    'created_at' => date('d/m/Y'),
                    'montant_ttc' => '72.00',
                    'lines' => [
                        [
                            'label' => 'Réparation pneu avant',
                            'quantite' => 1,
                            'prix_ht_snapshot' => 45.00
                        ],
                        [
                            'label' => 'Chambre à air',
                            'quantite' => 1,
                            'prix_ht_snapshot' => 15.00
                        ]
                    ]
                ],
                'payment' => [
                    'method' => 'CB',
                    'paid_at' => date('d/m/Y')
                ]
            ];

            // Générer le PDF via PdfService
            $pdfService = ($this->container['get'])('pdf');
            $pdf = $pdfService->renderPdf('pdf/facture.twig', $mockData);

            // Retourner le PDF inline
            $response->getBody()->write($pdf);
            return $response
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', 'inline; filename="apercu-facture.pdf"');
        } catch (\Exception $e) {
            // En cas d'erreur, afficher un message
            $response->getBody()->write("Erreur lors de la génération de l'aperçu : " . $e->getMessage());
            return $response->withStatus(500);
        }
    }

    /**
     * Génère un aperçu PDF de devis avec les données mockées
     */
    public function previewQuote(Request $request, Response $response): Response
    {
        if (!$this->companyProfileService || !$this->twig) {
            return $response->withStatus(500);
        }

        try {
            // Données mockées pour l'aperçu
            $profile = $this->companyProfileService->getProfile();
            
            // Vérifier si un logo temporaire est fourni
            $queryParams = $request->getQueryParams();
            if (isset($queryParams['logo_temp'])) {
                $profile['logo_path'] = $queryParams['logo_temp'];
            }
            
            $mockData = [
                'company' => $profile,
                'client' => [
                    'name' => 'Client test',
                    'address' => '1 rue de l\'examen',
                    'phone' => '06 00 00 00 00',
                    'email' => 'client.test@example.com'
                ],
                'meta' => [
                    'brand' => 'Marque test'
                ],
                'devis' => [
                    'numero' => 'PREVIEW',
                    'created_at' => date('d/m/Y'),
                    'montant_ttc' => '72.00',
                    'lines' => [
                        [
                            'label' => 'Réparation pneu avant',
                            'quantite' => 1,
                            'prix_ht_snapshot' => 45.00
                        ],
                        [
                            'label' => 'Chambre à air',
                            'quantite' => 1,
                            'prix_ht_snapshot' => 15.00
                        ]
                    ]
                ]
            ];

            // Générer le PDF via PdfService
            $pdfService = ($this->container['get'])('pdf');
            $pdf = $pdfService->renderPdf('pdf/devis.twig', $mockData);

            // Retourner le PDF inline
            $response->getBody()->write($pdf);
            return $response
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', 'inline; filename="apercu-devis.pdf"');
        } catch (\Exception $e) {
            // En cas d'erreur, afficher un message
            $response->getBody()->write("Erreur lors de la génération de l'aperçu : " . $e->getMessage());
            return $response->withStatus(500);
        }
    }
}
