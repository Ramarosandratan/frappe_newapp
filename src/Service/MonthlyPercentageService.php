<?php

namespace App\Service;

use App\Repository\MonthlyPercentageRepository;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des pourcentages mensuels
 * 
 * Ce service gère l'application de pourcentages différents selon le mois
 * pour les composants de salaire. Il permet d'ajuster automatiquement
 * les montants selon des variations saisonnières ou autres.
 * 
 * Fonctionnalités principales :
 * - Application de pourcentages mensuels aux valeurs de base
 * - Sauvegarde et récupération des pourcentages en base de données
 * - Validation des valeurs et limitation des pourcentages extrêmes
 * - Logs détaillés pour le suivi des modifications
 * 
 * Exemple d'utilisation :
 * - Salaire de base : 1000€
 * - Pourcentage janvier : +10% → 1100€
 * - Pourcentage février : -5% → 950€
 */
class MonthlyPercentageService
{
    /** @var MonthlyPercentageRepository Repository pour accéder aux données */
    private MonthlyPercentageRepository $monthlyPercentageRepository;
    
    /** @var LoggerInterface Logger pour tracer les opérations */
    private LoggerInterface $logger;

    /**
     * Constructeur - Injection des dépendances
     * 
     * @param MonthlyPercentageRepository $monthlyPercentageRepository Repository des pourcentages
     * @param LoggerInterface $logger Logger Symfony
     */
    public function __construct(
        MonthlyPercentageRepository $monthlyPercentageRepository,
        LoggerInterface $logger
    ) {
        $this->monthlyPercentageRepository = $monthlyPercentageRepository;
        $this->logger = $logger;
    }

    /**
     * Applique le pourcentage mensuel à une valeur de base
     * 
     * Cette méthode est au cœur du système de pourcentages mensuels.
     * Elle récupère le pourcentage défini pour un mois et composant donnés,
     * puis calcule la nouvelle valeur en appliquant ce pourcentage.
     * 
     * Formule : nouvelle_valeur = valeur_base * (1 + pourcentage/100)
     * 
     * @param float $baseValue Valeur de base à modifier
     * @param int $month Mois (1-12) pour lequel appliquer le pourcentage
     * @param string $component Nom du composant de salaire
     * @return float Nouvelle valeur avec le pourcentage appliqué
     */
    public function applyMonthlyPercentage(float $baseValue, int $month, string $component): float
    {
        try {
            // === VALIDATION DES PARAMÈTRES D'ENTRÉE ===
            
            // Vérifier que le mois est valide (1-12)
            if ($month < 1 || $month > 12) {
                $this->logger->warning("Invalid month provided for monthly percentage", [
                    'month' => $month,
                    'component' => $component,
                    'base_value' => $baseValue
                ]);
                return $baseValue; // Retourner la valeur inchangée
            }
            
            // Vérifier que la valeur de base n'est pas négative
            if ($baseValue < 0) {
                $this->logger->warning("Negative base value provided for monthly percentage", [
                    'month' => $month,
                    'component' => $component,
                    'base_value' => $baseValue
                ]);
                return $baseValue; // Retourner la valeur inchangée
            }
            
            $monthlyPercentage = $this->monthlyPercentageRepository->findByMonthAndComponent($month, $component);
            
            if (!$monthlyPercentage) {
                // Si aucun pourcentage n'est défini pour ce mois, retourner la valeur de base
                $this->logger->debug("No monthly percentage defined, using base value", [
                    'component' => $component,
                    'month' => $month,
                    'base_value' => $baseValue
                ]);
                return $baseValue;
            }
            
            $percentage = $monthlyPercentage->getPercentage();
            
            // Validation du pourcentage (éviter des valeurs extrêmes)
            if ($percentage < -100) {
                $this->logger->warning("Percentage too low, capping at -100%", [
                    'component' => $component,
                    'month' => $month,
                    'original_percentage' => $percentage
                ]);
                $percentage = -100;
            }
            
            if ($percentage > 1000) {
                $this->logger->warning("Percentage too high, capping at 1000%", [
                    'component' => $component,
                    'month' => $month,
                    'original_percentage' => $percentage
                ]);
                $percentage = 1000;
            }
            
            $newValue = $baseValue * (1 + ($percentage / 100));
            
            // S'assurer que la nouvelle valeur n'est pas négative
            if ($newValue < 0) {
                $this->logger->warning("Calculated value is negative, setting to 0", [
                    'component' => $component,
                    'month' => $month,
                    'base_value' => $baseValue,
                    'percentage' => $percentage,
                    'calculated_value' => $newValue
                ]);
                $newValue = 0;
            }
            
            $this->logger->info("Applied monthly percentage", [
                'component' => $component,
                'month' => $month,
                'base_value' => $baseValue,
                'percentage' => $percentage,
                'new_value' => $newValue,
                'change' => $newValue - $baseValue
            ]);
            
            return round($newValue, 2); // Arrondir à 2 décimales
            
        } catch (\Exception $e) {
            $this->logger->error("Error applying monthly percentage", [
                'component' => $component,
                'month' => $month,
                'base_value' => $baseValue,
                'error' => $e->getMessage()
            ]);
            
            // En cas d'erreur, retourner la valeur de base
            return $baseValue;
        }
    }

    /**
     * Sauvegarde les pourcentages mensuels pour un composant
     */
    public function saveMonthlyPercentages(string $component, array $percentages): void
    {
        $this->logger->info("Saving monthly percentages", [
            'component' => $component,
            'percentages' => $percentages
        ]);

        // Supprimer les anciens pourcentages pour ce composant
        $this->monthlyPercentageRepository->deleteByComponent($component);

        // Sauvegarder les nouveaux pourcentages
        for ($month = 1; $month <= 12; $month++) {
            if (isset($percentages[$month]) && $percentages[$month] !== '') {
                $percentage = (float) $percentages[$month];
                $this->monthlyPercentageRepository->saveOrUpdate($month, $component, $percentage);
            }
        }
    }

    /**
     * Récupère les pourcentages mensuels pour un composant
     */
    public function getMonthlyPercentages(string $component): array
    {
        $percentages = [];
        $monthlyPercentages = $this->monthlyPercentageRepository->findByComponent($component);
        
        foreach ($monthlyPercentages as $monthlyPercentage) {
            $percentages[$monthlyPercentage->getMonth()] = $monthlyPercentage->getPercentage();
        }
        
        return $percentages;
    }

    /**
     * Vérifie si des pourcentages mensuels sont définis pour un composant
     */
    public function hasMonthlyPercentages(string $component): bool
    {
        $percentages = $this->monthlyPercentageRepository->findByComponent($component);
        return !empty($percentages);
    }

    /**
     * Obtient les noms des mois en français
     */
    public function getMonthNames(): array
    {
        return [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre'
        ];
    }
}