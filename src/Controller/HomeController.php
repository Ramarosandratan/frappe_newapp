<?php

namespace App\Controller;

use App\Service\ErpNextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class HomeController extends AbstractController
{
    private $erpNextService;
    private $logger;

    public function __construct(ErpNextService $erpNextService, LoggerInterface $logger)
    {
        $this->erpNextService = $erpNextService;
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        try {
            // Récupérer quelques statistiques de base
            $employeeCount = count($this->erpNextService->getEmployees());
            $salaryStructureCount = count($this->erpNextService->getSalaryStructures());
            
            // Récupérer les fiches de paie du mois en cours
            $currentMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            
            $currentMonthSlips = $this->erpNextService->getSalarySlipsByPeriod($currentMonth, $endOfMonth);
            $currentMonthSlipCount = count($currentMonthSlips);
            
            // Calculer le total des salaires du mois en cours
            $totalGrossPay = 0;
            $totalNetPay = 0;
            
            foreach ($currentMonthSlips as $slip) {
                $totalGrossPay += $slip['gross_pay'] ?? 0;
                $totalNetPay += $slip['net_pay'] ?? 0;
            }
            
            return $this->render('home/index.html.twig', [
                'employeeCount' => $employeeCount,
                'salaryStructureCount' => $salaryStructureCount,
                'currentMonthSlipCount' => $currentMonthSlipCount,
                'totalGrossPay' => $totalGrossPay,
                'totalNetPay' => $totalNetPay,
                'currentMonth' => new \DateTime($currentMonth)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load home page statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->render('home/index.html.twig', [
                'error' => $e->getMessage()
            ]);
        }
    }
}