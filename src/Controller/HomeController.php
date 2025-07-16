<?php

namespace App\Controller;

use App\Service\ErpNextService;
use App\Service\ChangeHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

/**
 * Contrôleur pour la page d'accueil de l'application.
 * Gère l'affichage des statistiques générales et des informations du mois en cours.
 */
class HomeController extends AbstractController
{
    private ErpNextService $erpNextService;
    private LoggerInterface $logger;
    private ChangeHistoryService $changeHistoryService;

    /**
     * Constructeur du HomeController.
     *
     * @param ErpNextService $erpNextService Service pour interagir avec l'API ERPNext.
     * @param LoggerInterface $logger Service de journalisation pour enregistrer les erreurs.
     * @param ChangeHistoryService $changeHistoryService Service pour l'historique des modifications.
     */
    public function __construct(
        ErpNextService $erpNextService, 
        LoggerInterface $logger,
        ChangeHistoryService $changeHistoryService
    ) {
        $this->erpNextService = $erpNextService;
        $this->logger = $logger;
        $this->changeHistoryService = $changeHistoryService;
    }

    /**
     * Affiche la page d'accueil avec des statistiques récapitulatives.
     *
     * Cette méthode récupère le nombre d'employés, le nombre de structures salariales,
     * les fiches de paie du mois en cours et calcule les totaux bruts et nets.
     * En cas d'erreur, elle journalise l'exception et affiche un message d'erreur.
     *
     * @return Response La réponse HTTP rendant la vue de la page d'accueil.
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        try {
            // Récupérer quelques statistiques de base (nombre d'employés et de structures salariales)
            $employeeCount = count($this->erpNextService->getEmployees());
            $salaryStructureCount = count($this->erpNextService->getSalaryStructures());
            
            // Définir la période pour les fiches de paie du mois en cours
            $currentMonth = date('Y-m-01'); // Premier jour du mois actuel
            $endOfMonth = date('Y-m-t');   // Dernier jour du mois actuel
            
            // Récupérer les fiches de paie pour la période définie
            $currentMonthSlips = $this->erpNextService->getSalarySlipsByPeriod($currentMonth, $endOfMonth);
            $currentMonthSlipCount = count($currentMonthSlips);
            
            // Initialiser les totaux pour le salaire brut et net
            $totalGrossPay = 0;
            $totalNetPay = 0;
            
            // Parcourir les fiches de paie pour calculer les totaux
            foreach ($currentMonthSlips as $slip) {
                $totalGrossPay += $slip['gross_pay'] ?? 0; // Ajouter le salaire brut, 0 si non défini
                $totalNetPay += $slip['net_pay'] ?? 0;     // Ajouter le salaire net, 0 si non défini
            }
            
            // Récupérer l'historique récent des modifications (10 dernières)
            $recentHistory = $this->changeHistoryService->getRecentHistory(10);
            
            // Récupérer les statistiques d'aujourd'hui
            $today = new \DateTime();
            $todayStart = (clone $today)->setTime(0, 0, 0);
            $todayEnd = (clone $today)->setTime(23, 59, 59);
            $todayStats = $this->changeHistoryService->getStatistics($todayStart, $todayEnd);
            
            // Compter le total des modifications d'aujourd'hui
            $todayModifications = 0;
            foreach ($todayStats as $entityStats) {
                $todayModifications += array_sum($entityStats);
            }
            
            // Rendre la vue de la page d'accueil avec les données collectées
            return $this->render('home/index.html.twig', [
                'employeeCount' => $employeeCount,             // Nombre total d'employés
                'salaryStructureCount' => $salaryStructureCount, // Nombre total de structures salariales
                'currentMonthSlipCount' => $currentMonthSlipCount, // Nombre de fiches de paie pour le mois en cours
                'totalGrossPay' => $totalGrossPay,             // Total du salaire brut pour le mois en cours
                'totalNetPay' => $totalNetPay,                 // Total du salaire net pour le mois en cours
                'currentMonth' => new \DateTime($currentMonth), // Objet DateTime représentant le mois en cours
                'recentHistory' => $recentHistory,             // Historique récent des modifications
                'todayModifications' => $todayModifications    // Nombre de modifications aujourd'hui
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur, journaliser l'exception avec le message et la trace
            $this->logger->error('Failed to load home page statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Rendre la vue de la page d'accueil en affichant le message d'erreur
            return $this->render('home/index.html.twig', [
                'error' => $e->getMessage()
            ]);
        }
    }
}