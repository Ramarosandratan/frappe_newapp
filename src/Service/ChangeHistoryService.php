<?php

namespace App\Service;

use App\Entity\ChangeHistory;
use App\Repository\ChangeHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class ChangeHistoryService
{
    private EntityManagerInterface $entityManager;
    private ChangeHistoryRepository $repository;
    private LoggerInterface $logger;
    private RequestStack $requestStack;
    private ?Security $security;

    public function __construct(
        EntityManagerInterface $entityManager,
        ChangeHistoryRepository $repository,
        LoggerInterface $logger,
        RequestStack $requestStack,
        Security $security = null
    ) {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->security = $security;
    }

    /**
     * Enregistre une modification dans l'historique
     */
    public function logChange(
        string $entityType,
        string $entityId,
        string $fieldName,
        $oldValue,
        $newValue,
        string $action = 'UPDATE',
        ?string $reason = null,
        ?array $metadata = null
    ): void {
        try {
            $changeHistory = new ChangeHistory();
            $changeHistory->setEntityType($entityType);
            $changeHistory->setEntityId($entityId);
            $changeHistory->setFieldName($fieldName);
            $changeHistory->setOldValue($this->formatValue($oldValue));
            $changeHistory->setNewValue($this->formatValue($newValue));
            $changeHistory->setAction($action);
            $changeHistory->setReason($reason);
            $changeHistory->setMetadata($metadata);

            // Récupérer les informations de l'utilisateur actuel
            $this->setUserInfo($changeHistory);

            // Récupérer les informations de la requête
            $this->setRequestInfo($changeHistory);

            $this->entityManager->persist($changeHistory);
            $this->entityManager->flush();

            $this->logger->info('Change logged successfully', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'field_name' => $fieldName,
                'action' => $action
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to log change', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'field_name' => $fieldName,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Enregistre plusieurs modifications en une seule transaction
     */
    public function logMultipleChanges(
        string $entityType,
        string $entityId,
        array $changes,
        string $action = 'UPDATE',
        ?string $reason = null,
        ?array $metadata = null
    ): void {
        try {
            $this->entityManager->beginTransaction();

            foreach ($changes as $fieldName => $values) {
                $oldValue = $values['old'] ?? null;
                $newValue = $values['new'] ?? null;

                // Ne pas enregistrer si les valeurs sont identiques
                if ($this->formatValue($oldValue) === $this->formatValue($newValue)) {
                    continue;
                }

                $changeHistory = new ChangeHistory();
                $changeHistory->setEntityType($entityType);
                $changeHistory->setEntityId($entityId);
                $changeHistory->setFieldName($fieldName);
                $changeHistory->setOldValue($this->formatValue($oldValue));
                $changeHistory->setNewValue($this->formatValue($newValue));
                $changeHistory->setAction($action);
                $changeHistory->setReason($reason);
                $changeHistory->setMetadata($metadata);

                $this->setUserInfo($changeHistory);
                $this->setRequestInfo($changeHistory);

                $this->entityManager->persist($changeHistory);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Multiple changes logged successfully', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'changes_count' => count($changes),
                'action' => $action
            ]);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to log multiple changes', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Enregistre la création d'une entité
     */
    public function logCreation(
        string $entityType,
        string $entityId,
        array $initialData = [],
        ?string $reason = null,
        ?array $metadata = null
    ): void {
        foreach ($initialData as $fieldName => $value) {
            $this->logChange(
                $entityType,
                $entityId,
                $fieldName,
                null,
                $value,
                'CREATE',
                $reason,
                $metadata
            );
        }
    }

    /**
     * Enregistre la suppression d'une entité
     */
    public function logDeletion(
        string $entityType,
        string $entityId,
        array $finalData = [],
        ?string $reason = null,
        ?array $metadata = null
    ): void {
        foreach ($finalData as $fieldName => $value) {
            $this->logChange(
                $entityType,
                $entityId,
                $fieldName,
                $value,
                null,
                'DELETE',
                $reason,
                $metadata
            );
        }
    }

    /**
     * Récupère l'historique pour une entité
     */
    public function getEntityHistory(string $entityType, string $entityId, int $limit = 50): array
    {
        return $this->repository->findByEntity($entityType, $entityId, $limit);
    }

    /**
     * Récupère l'historique par utilisateur
     */
    public function getUserHistory(string $userId, int $limit = 100): array
    {
        return $this->repository->findByUser($userId, $limit);
    }

    /**
     * Récupère l'historique par période
     */
    public function getHistoryByDateRange(\DateTime $startDate, \DateTime $endDate, int $limit = 200): array
    {
        return $this->repository->findByDateRange($startDate, $endDate, $limit);
    }

    /**
     * Recherche dans l'historique avec filtres
     */
    public function searchHistory(array $filters = [], int $limit = 100): array
    {
        return $this->repository->searchHistory($filters, $limit);
    }

    /**
     * Récupère les statistiques des modifications
     */
    public function getChangeStatistics(\DateTime $startDate = null, \DateTime $endDate = null): array
    {
        return $this->repository->getChangeStatistics($startDate, $endDate);
    }

    /**
     * Formate une valeur pour le stockage
     */
    private function formatValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * Définit les informations utilisateur
     */
    private function setUserInfo(ChangeHistory $changeHistory): void
    {
        if ($this->security && $this->security->getUser()) {
            $user = $this->security->getUser();
            $changeHistory->setUserId($user->getUserIdentifier());
            
            // Si l'utilisateur a une méthode getName ou getFullName
            if (method_exists($user, 'getName')) {
                $changeHistory->setUserName($user->getName());
            } elseif (method_exists($user, 'getFullName')) {
                $changeHistory->setUserName($user->getFullName());
            } else {
                $changeHistory->setUserName($user->getUserIdentifier());
            }
        }
    }

    /**
     * Définit les informations de la requête
     */
    private function setRequestInfo(ChangeHistory $changeHistory): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $changeHistory->setIpAddress($request->getClientIp());
            $changeHistory->setUserAgent($request->headers->get('User-Agent'));
        }
    }

    /**
     * Nettoie l'historique ancien
     */
    public function cleanOldHistory(int $daysToKeep = 365): int
    {
        return $this->repository->cleanOldHistory($daysToKeep);
    }

    /**
     * Compte les enregistrements qui seraient supprimés
     */
    public function countOldHistory(int $daysToKeep = 365): int
    {
        return $this->repository->countOldHistory($daysToKeep);
    }

    /**
     * Récupère les statistiques des modifications
     */
    public function getStatistics(\DateTime $startDate = null, \DateTime $endDate = null): array
    {
        return $this->repository->getStatistics($startDate, $endDate);
    }

    /**
     * Récupère l'historique récent
     */
    public function getRecentHistory(int $limit = 10): array
    {
        return $this->repository->searchHistory([], $limit);
    }

    /**
     * Méthodes spécifiques pour les fiches de paie
     */
    public function logPayslipChange(
        string $payslipId,
        string $fieldName,
        $oldValue,
        $newValue,
        ?string $reason = null
    ): void {
        $this->logChange(
            'Salary Slip',
            $payslipId,
            $fieldName,
            $oldValue,
            $newValue,
            'UPDATE',
            $reason,
            ['source' => 'payslip_modification']
        );
    }

    /**
     * Méthodes spécifiques pour les employés
     */
    public function logEmployeeChange(
        string $employeeId,
        string $fieldName,
        $oldValue,
        $newValue,
        ?string $reason = null
    ): void {
        $this->logChange(
            'Employee',
            $employeeId,
            $fieldName,
            $oldValue,
            $newValue,
            'UPDATE',
            $reason,
            ['source' => 'employee_modification']
        );
    }

    /**
     * Méthodes spécifiques pour les pourcentages mensuels
     */
    public function logMonthlyPercentageChange(
        string $component,
        int $month,
        $oldValue,
        $newValue,
        ?string $reason = null
    ): void {
        $this->logChange(
            'Monthly Percentage',
            "{$component}_{$month}",
            $fieldName = 'percentage',
            $oldValue,
            $newValue,
            'UPDATE',
            $reason,
            ['source' => 'monthly_percentage_modification', 'component' => $component, 'month' => $month]
        );
    }
}