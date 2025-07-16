<?php

namespace App\Controller;

use App\Service\ChangeHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur de test pour démontrer le système d'historique
 */
class TestHistoryController extends AbstractController
{
    private ChangeHistoryService $changeHistoryService;

    public function __construct(ChangeHistoryService $changeHistoryService)
    {
        $this->changeHistoryService = $changeHistoryService;
    }

    /**
     * Page de test pour simuler des modifications
     */
    #[Route('/test-history', name: 'app_test_history')]
    public function testPage(): Response
    {
        return $this->render('test_history/index.html.twig');
    }

    /**
     * Simule une modification de salaire de base
     */
    #[Route('/test-history/modify-salary', name: 'app_test_modify_salary', methods: ['POST'])]
    public function modifySalary(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $payslipId = $data['payslip_id'] ?? 'SAL-TEST-001';
        $oldSalary = $data['old_salary'] ?? 2500.00;
        $newSalary = $data['new_salary'] ?? 2800.00;
        $reason = $data['reason'] ?? 'Test de modification via interface web';

        // Enregistrer la modification dans l'historique
        $this->changeHistoryService->logPayslipChange(
            $payslipId,
            'base_salary',
            $oldSalary,
            $newSalary,
            $reason
        );

        return new JsonResponse([
            'success' => true,
            'message' => 'Modification enregistrée avec succès',
            'data' => [
                'payslip_id' => $payslipId,
                'old_salary' => $oldSalary,
                'new_salary' => $newSalary,
                'reason' => $reason,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Simule une modification de pourcentage mensuel
     */
    #[Route('/test-history/modify-percentage', name: 'app_test_modify_percentage', methods: ['POST'])]
    public function modifyPercentage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $component = $data['component'] ?? 'Prime transport';
        $month = $data['month'] ?? 3;
        $oldPercentage = $data['old_percentage'] ?? 10.0;
        $newPercentage = $data['new_percentage'] ?? 15.0;
        $reason = $data['reason'] ?? 'Test de modification de pourcentage via interface web';

        // Enregistrer la modification dans l'historique
        $this->changeHistoryService->logMonthlyPercentageChange(
            $component,
            $month,
            $oldPercentage,
            $newPercentage,
            $reason
        );

        return new JsonResponse([
            'success' => true,
            'message' => 'Pourcentage modifié avec succès',
            'data' => [
                'component' => $component,
                'month' => $month,
                'old_percentage' => $oldPercentage,
                'new_percentage' => $newPercentage,
                'reason' => $reason,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Récupère les dernières modifications pour affichage
     */
    #[Route('/test-history/recent', name: 'app_test_recent_history', methods: ['GET'])]
    public function getRecentHistory(): JsonResponse
    {
        $recentHistory = $this->changeHistoryService->getRecentHistory(10);
        
        $data = [];
        foreach ($recentHistory as $change) {
            $data[] = [
                'id' => $change->getId(),
                'entity_type' => $change->getEntityType(),
                'entity_id' => $change->getEntityId(),
                'field_name' => $change->getFieldName(),
                'old_value' => $change->getFormattedOldValue(),
                'new_value' => $change->getFormattedNewValue(),
                'action' => $change->getActionLabel(),
                'action_class' => $change->getActionBadgeClass(),
                'changed_at' => $change->getChangedAt()->format('d/m/Y H:i:s'),
                'reason' => $change->getReason()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'count' => count($data)
        ]);
    }

    /**
     * Récupère les statistiques du jour
     */
    #[Route('/test-history/stats', name: 'app_test_history_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        $today = new \DateTime();
        $todayStart = (clone $today)->setTime(0, 0, 0);
        $todayEnd = (clone $today)->setTime(23, 59, 59);
        
        $stats = $this->changeHistoryService->getStatistics($todayStart, $todayEnd);
        
        $totalToday = 0;
        foreach ($stats as $entityStats) {
            $totalToday += array_sum($entityStats);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'today_total' => $totalToday,
                'by_entity' => $stats,
                'date' => $today->format('Y-m-d')
            ]
        ]);
    }
}