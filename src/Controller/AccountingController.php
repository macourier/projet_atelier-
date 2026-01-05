<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Service\AccountingKpiService;
use App\Service\AccountingTransactionsService;
use App\Service\AccountingExportService;
use App\Service\AccountingPaymentsService;
use App\Service\AccountingExportLogService;

class AccountingController
{
    private array $container;
    private $twig;
    private ?AccountingKpiService $kpiService;
    private ?AccountingTransactionsService $transactionsService;
    private ?AccountingExportService $exportService;
    private ?AccountingPaymentsService $paymentsService;
    private ?AccountingExportLogService $exportLogService;

    public function __construct(array $container = [])
    {
        $this->container = $container;
        $this->twig = $container['twig'] ?? null;
        // Initialiser les services si PDO est disponible
        $pdo = $container['pdo'] ?? null;
        if ($pdo) {
            $this->kpiService = new AccountingKpiService($pdo);
            $this->transactionsService = new AccountingTransactionsService($pdo);
            $this->exportService = new AccountingExportService($pdo, $this->kpiService);
            $this->paymentsService = new AccountingPaymentsService($pdo);
            $this->exportLogService = new AccountingExportLogService($pdo);
        }
    }

    public function index(Request $request, Response $response): Response
    {
        // Récupérer les query params
        $queryParams = $request->getQueryParams();
        
        // Déterminer le mois/année actuel
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('n'); // 1-12
        
        // Utiliser les query params ou fallback sur le mois courant
        $year = isset($queryParams['year']) ? (int)$queryParams['year'] : $currentYear;
        $month = isset($queryParams['month']) ? (int)$queryParams['month'] : $currentMonth;
        
        // Récupérer et valider le filtre par méthode de paiement
        $selectedMethod = isset($queryParams['method']) ? (string)$queryParams['method'] : null;
        $validMethods = ['CB', 'ESPECE'];
        if ($selectedMethod !== '' && !in_array($selectedMethod, $validMethods)) {
            $selectedMethod = null;
        }
        if ($selectedMethod === '') {
            $selectedMethod = null;
        }
        
        // Valider year (2000-2100) et month (1-12)
        if ($year < 2000 || $year > 2100) {
            $year = $currentYear;
        }
        if ($month < 1 || $month > 12) {
            $month = $currentMonth;
        }
        
        // Calculer le mois précédent
        $prevYear = $year;
        $prevMonth = $month - 1;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        $prevUrl = "/admin/accounting?year={$prevYear}&month={$prevMonth}" . ($selectedMethod ? "&method={$selectedMethod}" : '');
        
        // Calculer le mois suivant
        $nextYear = $year;
        $nextMonth = $month + 1;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }
        $nextUrl = "/admin/accounting?year={$nextYear}&month={$nextMonth}" . ($selectedMethod ? "&method={$selectedMethod}" : '');
        
        // Label de la période (ex: "Octobre 2025")
        $moisFr = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        $periodLabel = $moisFr[$month] . ' ' . $year;
        
        // Calculer les KPI via le service (MOIS COMPLET, sans filtre method)
        $kpis = ['ca_eur' => 0, 'clients' => 0, 'avg_basket_eur' => 0];
        if ($this->kpiService) {
            $kpis = $this->kpiService->getMonthlyKpis($year, $month);
        }
        
        // Formater les valeurs en français
        $caDisplay = number_format($kpis['ca_eur'], 2, ',', ' ') . ' €';
        $clientsDisplay = (string)$kpis['clients'];
        $avgDisplay = number_format($kpis['avg_basket_eur'], 2, ',', ' ') . ' €';
        
        // Récupérer la ventilation par moyen de paiement
        $paymentBreakdown = [];
        if ($this->kpiService) {
            $paymentBreakdown = $this->kpiService->getMonthlyPaymentBreakdown($year, $month);
        }
        
        // Formatter la ventilation pour l'affichage
        $paymentBreakdownFormatted = [
            'CB' => number_format($paymentBreakdown['CB'] ?? 0, 2, ',', ' ') . ' €',
            'ESPECES' => number_format($paymentBreakdown['ESPECES'] ?? 0, 2, ',', ' ') . ' €',
            'VIREMENT' => number_format($paymentBreakdown['VIREMENT'] ?? 0, 2, ',', ' ') . ' €',
            'AUTRES' => number_format($paymentBreakdown['AUTRES'] ?? 0, 2, ',', ' ') . ' €'
        ];
        
        // Récupérer les paiements avec filtre optionnel par méthode
        $payments = [];
        if ($this->paymentsService) {
            $payments = $this->paymentsService->getMonthlyPayments($year, $month, $selectedMethod);
        }
        
        // Options pour le select de filtre
        $methodOptions = [
            '' => 'Tous',
            'CB' => 'CB',
            'ESPECE' => 'Espèce'
        ];
        
        // Récupérer et supprimer le message d'erreur de session s'il existe
        $flashError = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : null;
        if ($flashError !== null) {
            unset($_SESSION['flash_error']);
        }

        // Récupérer le dernier export pour ce mois
        $lastExportDisplay = '—';
        if ($this->exportLogService) {
            $lastExport = $this->exportLogService->getLastMonthlyExport($year, $month);
            if ($lastExport && isset($lastExport['exported_at'])) {
                // Format FR : dd/mm/yyyy HH:MM
                $exportDate = new \DateTime($lastExport['exported_at']);
                $lastExportDisplay = $exportDate->format('d/m/Y H:i');
            }
        }
        
        $html = $this->twig->render('accounting/index.twig', [
            'periodLabel' => $periodLabel,
            'year' => $year,
            'month' => $month,
            'prevUrl' => $prevUrl,
            'nextUrl' => $nextUrl,
            'ca_display' => $caDisplay,
            'clients_display' => $clientsDisplay,
            'avg_display' => $avgDisplay,
            'payment_breakdown' => $paymentBreakdownFormatted,
            'payments' => $payments,
            'payments_count' => count($payments),
            'selected_method' => $selectedMethod,
            'method_options' => $methodOptions,
            'flash_error' => $flashError,
            'last_export_display' => $lastExportDisplay
        ]);
        
        $response->getBody()->write($html);
        return $response;
    }

    public function export(Request $request, Response $response): Response
    {
        if (!$this->exportService) {
            return $response->withStatus(500);
        }

        // Récupérer les query params
        $queryParams = $request->getQueryParams();
        
        // Déterminer le mois/année actuel
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('n'); // 1-12
        
        // Utiliser les query params ou fallback sur le mois courant
        $year = isset($queryParams['year']) ? (int)$queryParams['year'] : $currentYear;
        $month = isset($queryParams['month']) ? (int)$queryParams['month'] : $currentMonth;
        
        // Valider year (2000-2100) et month (1-12)
        if ($year < 2000 || $year > 2100) {
            $year = $currentYear;
        }
        if ($month < 1 || $month > 12) {
            $month = $currentMonth;
        }

        try {
            // Générer l'export
            $result = $this->exportService->exportMonthly($year, $month);

            // Logger l'export réussi
            if ($this->exportLogService) {
                // Récupérer le username depuis la session si disponible
                $exportedBy = null;
                if (isset($_SESSION['user']) && isset($_SESSION['user']['username'])) {
                    $exportedBy = $_SESSION['user']['username'];
                }
                
                $this->exportLogService->logMonthlyExport(
                    $year,
                    $month,
                    $exportedBy,
                    $result['row_count'] ?? null
                );
            }

            // Écrire le contenu dans la réponse
            $response->getBody()->write($result['content']);

            return $response
                ->withHeader('Content-Type', $result['contentType'])
                ->withHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
                ->withStatus(200);
        } catch (\Exception $e) {
            // Gestion des erreurs (ex: trop de transactions)
            // Stocker le message en session pour l'afficher sur la page de redirection
            $_SESSION['flash_error'] = $e->getMessage();
            
            // Rediriger vers la page comptabilité avec les mêmes paramètres
            $redirectUrl = "/admin/accounting?year={$year}&month={$month}";
            return $response
                ->withHeader('Location', $redirectUrl)
                ->withStatus(302);
        }
    }
}
