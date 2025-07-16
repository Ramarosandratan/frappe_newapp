<?php

namespace App\Repository;

use App\Entity\ChangeHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChangeHistory>
 */
class ChangeHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChangeHistory::class);
    }

    /**
     * Récupère l'historique des modifications pour une entité spécifique
     */
    public function findByEntity(string $entityType, string $entityId, int $limit = 50): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.entityType = :entityType')
            ->andWhere('ch.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('ch.changedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère l'historique des modifications par utilisateur
     */
    public function findByUser(string $userId, int $limit = 100): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ch.changedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère l'historique des modifications par période
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate, int $limit = 200): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.changedAt >= :startDate')
            ->andWhere('ch.changedAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ch.changedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère l'historique des modifications par type d'entité
     */
    public function findByEntityType(string $entityType, int $limit = 100): array
    {
        return $this->createQueryBuilder('ch')
            ->where('ch.entityType = :entityType')
            ->setParameter('entityType', $entityType)
            ->orderBy('ch.changedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les statistiques des modifications
     */
    public function getChangeStatistics(\DateTime $startDate = null, \DateTime $endDate = null): array
    {
        $qb = $this->createQueryBuilder('ch')
            ->select('ch.entityType, ch.action, COUNT(ch.id) as count')
            ->groupBy('ch.entityType, ch.action');

        if ($startDate) {
            $qb->andWhere('ch.changedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('ch.changedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère les utilisateurs les plus actifs
     */
    public function getMostActiveUsers(int $limit = 10): array
    {
        return $this->createQueryBuilder('ch')
            ->select('ch.userId, ch.userName, COUNT(ch.id) as changeCount')
            ->where('ch.userId IS NOT NULL')
            ->groupBy('ch.userId, ch.userName')
            ->orderBy('changeCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }



    /**
     * Recherche dans l'historique avec filtres multiples
     */
    public function searchHistory(array $filters = [], int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('ch');

        if (!empty($filters['entityType'])) {
            $qb->andWhere('ch.entityType = :entityType')
               ->setParameter('entityType', $filters['entityType']);
        }

        if (!empty($filters['entityId'])) {
            $qb->andWhere('ch.entityId = :entityId')
               ->setParameter('entityId', $filters['entityId']);
        }

        if (!empty($filters['userId'])) {
            $qb->andWhere('ch.userId = :userId')
               ->setParameter('userId', $filters['userId']);
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('ch.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (!empty($filters['fieldName'])) {
            $qb->andWhere('ch.fieldName = :fieldName')
               ->setParameter('fieldName', $filters['fieldName']);
        }

        if (!empty($filters['startDate'])) {
            $qb->andWhere('ch.changedAt >= :startDate')
               ->setParameter('startDate', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $qb->andWhere('ch.changedAt <= :endDate')
               ->setParameter('endDate', $filters['endDate']);
        }

        return $qb->orderBy('ch.changedAt', 'DESC')
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Nettoie l'historique ancien
     */
    public function cleanOldHistory(int $daysToKeep = 365): int
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$daysToKeep} days");

        $qb = $this->createQueryBuilder('ch')
            ->delete()
            ->where('ch.changedAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate);

        return $qb->getQuery()->execute();
    }

    /**
     * Compte les enregistrements qui seraient supprimés
     */
    public function countOldHistory(int $daysToKeep = 365): int
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$daysToKeep} days");

        $qb = $this->createQueryBuilder('ch')
            ->select('COUNT(ch.id)')
            ->where('ch.changedAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les statistiques des modifications
     */
    public function getStatistics(\DateTime $startDate = null, \DateTime $endDate = null): array
    {
        $qb = $this->createQueryBuilder('ch')
            ->select('ch.entityType, ch.action, COUNT(ch.id) as count')
            ->groupBy('ch.entityType, ch.action');

        if ($startDate) {
            $qb->andWhere('ch.changedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('ch.changedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $results = $qb->getQuery()->getResult();
        
        $statistics = [];
        foreach ($results as $result) {
            $entityType = $result['entityType'];
            $action = $result['action'];
            $count = (int) $result['count'];
            
            if (!isset($statistics[$entityType])) {
                $statistics[$entityType] = [];
            }
            
            $statistics[$entityType][$action] = $count;
        }

        return $statistics;
    }
}