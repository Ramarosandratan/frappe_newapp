<?php

namespace App\Controller;

use App\Service\ChangeHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/history')]
class ChangeHistoryController extends AbstractController
{
    private ChangeHistoryService $changeHistoryService;

    public function __construct(ChangeHistoryService $changeHistoryService)
    {
        $this->changeHistoryService = $changeHistoryService;
    }

    /**
     * Page principale de l'historique des modifications
     */
    #[Route('/', name: 'app_change_history_index')]
    public function index(Request $request): Response
    {
        $filters = [
            'entityType' => null,
            'entityId' => null,
            'userId' => null,
            'action' => null,
            'fieldName' => null,
            'startDate' => null,
            'endDate' => null
        ];
        $limit = 100;

        // Récupérer les filtres depuis la requête
        if ($request->query->get('entity_type')) {
            $filters['entityType'] = $request->query->get('entity_type');
        }

        if ($request->query->get('entity_id')) {
            $filters['entityId'] = $request->query->get('entity_id');
        }

        if ($request->query->get('user_id')) {
            $filters['userId'] = $request->query->get('user_id');
        }

        if ($request->query->get('action')) {
            $filters['action'] = $request->query->get('action');
        }

        if ($request->query->get('field_name')) {
            $filters['fieldName'] = $request->query->get('field_name');
        }

        if ($request->query->get('start_date')) {
            $filters['startDate'] = new \DateTime($request->query->get('start_date'));
        }

        if ($request->query->get('end_date')) {
            $filters['endDate'] = new \DateTime($request->query->get('end_date'));
        }

        if ($request->query->get('limit')) {
            $limit = min((int) $request->query->get('limit'), 500); // Maximum 500
        }

        // Récupérer l'historique avec les filtres
        $history = $this->changeHistoryService->searchHistory($filters, $limit);

        // Récupérer les statistiques pour la période actuelle
        $startDate = $filters['startDate'] ?? (new \DateTime())->modify('-30 days');
        $endDate = $filters['endDate'] ?? new \DateTime();
        $statistics = $this->changeHistoryService->getStatistics($startDate, $endDate);

        return $this->render('change_history/index.html.twig', [
            'history' => $history,
            'statistics' => $statistics,
            'filters' => $filters,
            'limit' => $limit,
            'total_results' => count($history)
        ]);
    }

    /**
     * Historique pour une entité spécifique
     */
    #[Route('/entity/{entityType}/{entityId}', name: 'app_change_history_entity')]
    public function entityHistory(string $entityType, string $entityId, Request $request): Response
    {
        // Décoder l'entityId qui peut contenir des caractères encodés
        $entityId = urldecode($entityId);
        
        $limit = min((int) $request->query->get('limit', 50), 200);
        
        $history = $this->changeHistoryService->getEntityHistory($entityType, $entityId, $limit);

        return $this->render('change_history/entity.html.twig', [
            'history' => $history,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'limit' => $limit
        ]);
    }

    /**
     * Historique pour un utilisateur spécifique
     */
    #[Route('/user/{userId}', name: 'app_change_history_user')]
    public function userHistory(string $userId, Request $request): Response
    {
        $limit = min((int) $request->query->get('limit', 100), 300);
        
        $history = $this->changeHistoryService->getUserHistory($userId, $limit);

        return $this->render('change_history/user.html.twig', [
            'history' => $history,
            'userId' => $userId,
            'limit' => $limit
        ]);
    }

    /**
     * Statistiques des modifications
     */
    #[Route('/statistics', name: 'app_change_history_statistics')]
    public function statistics(Request $request): Response
    {
        $startDate = $request->query->get('start_date') 
            ? new \DateTime($request->query->get('start_date'))
            : (new \DateTime())->modify('-30 days');
            
        $endDate = $request->query->get('end_date')
            ? new \DateTime($request->query->get('end_date'))
            : new \DateTime();

        $statistics = $this->changeHistoryService->getStatistics($startDate, $endDate);
        
        // Les statistiques sont déjà organisées par type d'entité et action
        $organizedStats = $statistics;

        return $this->render('change_history/statistics.html.twig', [
            'statistics' => $organizedStats,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'rawStatistics' => $statistics
        ]);
    }

    /**
     * Export de l'historique en CSV
     */
    #[Route('/export', name: 'app_change_history_export')]
    public function export(Request $request): Response
    {
        $filters = [];
        
        // Récupérer les mêmes filtres que pour l'index
        if ($request->query->get('entity_type')) {
            $filters['entityType'] = $request->query->get('entity_type');
        }

        if ($request->query->get('start_date')) {
            $filters['startDate'] = new \DateTime($request->query->get('start_date'));
        }

        if ($request->query->get('end_date')) {
            $filters['endDate'] = new \DateTime($request->query->get('end_date'));
        }

        $history = $this->changeHistoryService->searchHistory($filters, 1000); // Maximum 1000 pour l'export

        // Créer le contenu CSV
        $csvContent = "Date/Heure,Type d'entité,ID Entité,Champ,Ancienne valeur,Nouvelle valeur,Action,Utilisateur,Raison\n";
        
        foreach ($history as $change) {
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $change->getChangedAt()->format('Y-m-d H:i:s'),
                $this->escapeCsv($change->getEntityType()),
                $this->escapeCsv($change->getEntityId()),
                $this->escapeCsv($change->getFieldName()),
                $this->escapeCsv($change->getOldValue() ?? ''),
                $this->escapeCsv($change->getNewValue() ?? ''),
                $this->escapeCsv($change->getActionLabel()),
                $this->escapeCsv($change->getUserName() ?? ''),
                $this->escapeCsv($change->getReason() ?? '')
            );
        }

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="historique_modifications_' . date('Y-m-d_H-i-s') . '.csv"');

        return $response;
    }

    /**
     * Échappe les valeurs pour CSV
     */
    private function escapeCsv(string $value): string
    {
        // Remplacer les guillemets par des guillemets doublés et entourer de guillemets si nécessaire
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}