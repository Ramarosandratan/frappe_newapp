<?php

namespace App\Repository;

use App\Entity\MonthlyPercentage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MonthlyPercentageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonthlyPercentage::class);
    }

    /**
     * Trouve les pourcentages pour un composant donné
     */
    public function findByComponent(string $component): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.component = :component')
            ->setParameter('component', $component)
            ->orderBy('mp.month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le pourcentage pour un mois et un composant spécifiques
     */
    public function findByMonthAndComponent(int $month, string $component): ?MonthlyPercentage
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.month = :month')
            ->andWhere('mp.component = :component')
            ->setParameter('month', $month)
            ->setParameter('component', $component)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Sauvegarde ou met à jour un pourcentage mensuel
     */
    public function saveOrUpdate(int $month, string $component, float $percentage): MonthlyPercentage
    {
        $monthlyPercentage = $this->findByMonthAndComponent($month, $component);
        
        if (!$monthlyPercentage) {
            $monthlyPercentage = new MonthlyPercentage();
            $monthlyPercentage->setMonth($month);
            $monthlyPercentage->setComponent($component);
        }
        
        $monthlyPercentage->setPercentage($percentage);
        
        $this->getEntityManager()->persist($monthlyPercentage);
        $this->getEntityManager()->flush();
        
        return $monthlyPercentage;
    }

    /**
     * Supprime tous les pourcentages pour un composant donné
     */
    public function deleteByComponent(string $component): void
    {
        $this->createQueryBuilder('mp')
            ->delete()
            ->andWhere('mp.component = :component')
            ->setParameter('component', $component)
            ->getQuery()
            ->execute();
    }
}