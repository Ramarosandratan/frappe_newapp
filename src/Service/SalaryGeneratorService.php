<?php

namespace App\Service;

use DateTimeInterface;
use Psr\Log\LoggerInterface;

class SalaryGeneratorService
{
    private ErpNextService $erpNextService;
    private LoggerInterface $logger;

    public function __construct(
        ErpNextService $erpNextService,
        LoggerInterface $logger
    ) {
        $this->erpNextService = $erpNextService;
        $this->logger = $logger;
    }

    /**
     * Découpe une période en mois complets pour la génération de fiches de paie
     * 
     * @param DateTimeInterface $start Date de début de la période globale
     * @param DateTimeInterface $end Date de fin de la période globale
     * @return array Array de périodes mensuelles avec 'start' et 'end'
     */
    public function splitPeriodIntoMonths(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $periods = [];
        $current = clone $start;
        
        while ($current <= $end) {
            $monthStart = (clone $current)->modify('first day of this month');
            $monthEnd = (clone $current)->modify('last day of this month');
            
            // Ajustement des limites selon la période globale
            if ($monthStart < $start) {
                $monthStart = clone $start;
            }
            if ($monthEnd > $end) {
                $monthEnd = clone $end;
            }
            
            $periods[] = [
                'start' => clone $monthStart,
                'end' => clone $monthEnd
            ];
            
            // Passer au mois suivant
            $current = $monthEnd->modify('+1 day');
        }
        
        return $periods;
    }

    /**
     * Génère les fiches de paie pour tous les employés actifs pour la période spécifiée
     * Découpe automatiquement les périodes multi-mois en mois individuels
     * 
     * @param DateTimeInterface $startDate Date de début de la période
     * @param DateTimeInterface $endDate Date de fin de la période
     * @param bool $overwrite Écraser les fiches existantes
     * @param bool $useAverage Utiliser la moyenne des fiches précédentes
     * @param float|null $baseSalary Salaire de base spécifique (optionnel)
     * @return array{created: int, skipped: int, deleted: int, errors: array}
     */
    public function generate(DateTimeInterface $startDate, DateTimeInterface $endDate, bool $overwrite, bool $useAverage, ?float $baseSalary = null): array
    {
        $this->logger->info("Starting salary slip generation", [
            'period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            'overwrite' => $overwrite ? 'yes' : 'no',
            'use_average' => $useAverage ? 'yes' : 'no',
            'base_salary' => $baseSalary ? number_format($baseSalary, 2) : 'auto'
        ]);
        
        // Découper la période en mois individuels
        $monthlyPeriods = $this->splitPeriodIntoMonths($startDate, $endDate);
        $this->logger->info("Period split into monthly periods", [
            'total_periods' => count($monthlyPeriods),
            'periods' => array_map(function($period) {
                return $period['start']->format('Y-m-d') . ' to ' . $period['end']->format('Y-m-d');
            }, $monthlyPeriods)
        ]);
        
        try {
            $employees = $this->erpNextService->getActiveEmployees();
            $this->logger->info("Found active employees", ['count' => count($employees)]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to retrieve employees", ['error' => $e->getMessage()]);
            return ['created' => 0, 'skipped' => 0, 'deleted' => 0, 'errors' => ["Failed to retrieve employees: " . $e->getMessage()]];
        }
        
        $summary = ['created' => 0, 'skipped' => 0, 'deleted' => 0, 'errors' => []];

        // Traiter chaque employé pour chaque période mensuelle
        foreach ($employees as $employee) {
            $this->logger->info("Processing employee for all monthly periods", [
                'employee' => $employee['name'],
                'employee_name' => $employee['employee_name'] ?? 'Unknown',
                'periods_count' => count($monthlyPeriods)
            ]);
            
            // Générer les fiches pour chaque mois
            foreach ($monthlyPeriods as $periodIndex => $period) {
                $this->logger->info("Processing monthly period for employee", [
                    'employee' => $employee['name'],
                    'period_index' => $periodIndex + 1,
                    'period' => $period['start']->format('Y-m-d') . ' to ' . $period['end']->format('Y-m-d')
                ]);
                
                $monthResult = $this->generateSalaryForPeriod(
                    $employee,
                    $period['start'],
                    $period['end'],
                    $baseSalary,
                    $overwrite,
                    $useAverage
                );
                
                // Agréger les résultats
                $summary['created'] += $monthResult['created'];
                $summary['skipped'] += $monthResult['skipped'];
                $summary['deleted'] += $monthResult['deleted'];
                $summary['errors'] = array_merge($summary['errors'], $monthResult['errors']);
            }
        }
        
        $this->logger->info("Salary slip generation completed", [
            'created' => $summary['created'],
            'skipped' => $summary['skipped'],
            'deleted' => $summary['deleted'],
            'errors' => count($summary['errors'])
        ]);

        return $summary;
    }

    /**
     * Génère une fiche de paie pour un employé spécifique sur une période donnée
     * 
     * @param array $employee Données de l'employé
     * @param DateTimeInterface $startDate Date de début de la période
     * @param DateTimeInterface $endDate Date de fin de la période
     * @param float|null $manualSalaryValue Valeur manuelle du salaire (optionnel)
     * @param bool $overwriteExisting Écraser les fiches existantes
     * @param bool $useAverage Utiliser la moyenne des salaires précédents
     * @return array{created: int, skipped: int, deleted: int, errors: array}
     */
    private function generateSalaryForPeriod(
        array $employee,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        ?float $manualSalaryValue,
        bool $overwriteExisting,
        bool $useAverage
    ): array {
        $result = ['created' => 0, 'skipped' => 0, 'deleted' => 0, 'errors' => []];
        
        $this->logger->info("Generating salary for specific period", [
            'employee' => $employee['name'],
            'period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            'manual_salary' => $manualSalaryValue ? number_format($manualSalaryValue, 2) : 'auto'
        ]);
        
        try {
            // Vérifier les fiches existantes pour cette période spécifique
            $existingSlips = $this->erpNextService->getSalarySlips([
                'employee' => $employee['name'],
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);

            if (!empty($existingSlips) && !$overwriteExisting) {
                $this->logger->info("Skipping employee - salary slip already exists", [
                    'employee' => $employee['name'],
                    'slip' => $existingSlips[0]['name'] ?? 'Unknown'
                ]);
                $result['skipped']++;
                return $result;
            }
            
            if (!empty($existingSlips) && $overwriteExisting) {
                $this->logger->info("Deleting existing salary slips before creating new ones", [
                    'employee' => $employee['name'],
                    'existing_slips_count' => count($existingSlips)
                ]);
                
                // Supprimer les fiches existantes
                $deleteResult = $this->erpNextService->deleteExistingSalarySlips(
                    $employee['name'],
                    $startDate->format('Y-m-d'),
                    $endDate->format('Y-m-d')
                );
                
                if (!empty($deleteResult['errors'])) {
                    foreach ($deleteResult['errors'] as $error) {
                        $result['errors'][] = "Employee {$employee['name']}: $error";
                    }
                    
                    // Si la suppression échoue complètement, passer à l'employé suivant
                    if (empty($deleteResult['deleted'])) {
                        $this->logger->error("Could not delete any existing slips, skipping employee", [
                            'employee' => $employee['name'],
                            'errors' => $deleteResult['errors']
                        ]);
                        return $result;
                    } else {
                        // Si certaines suppressions ont réussi, continuer
                        $this->logger->warning("Some errors occurred while deleting existing slips, but continuing", [
                            'employee' => $employee['name'],
                            'deleted_count' => count($deleteResult['deleted']),
                            'errors' => $deleteResult['errors']
                        ]);
                        $result['deleted'] += count($deleteResult['deleted']);
                    }
                } else {
                    $this->logger->info("Successfully deleted existing salary slips", [
                        'employee' => $employee['name'],
                        'deleted_count' => count($deleteResult['deleted'])
                    ]);
                    $result['deleted'] += count($deleteResult['deleted']);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Error checking existing salary slips, continuing", [
                'employee' => $employee['name'],
                'error' => $e->getMessage()
            ]);
        }
        
        // Vérifier si l'employé a une structure salariale assignée
        $assignmentDate = (clone $startDate)->modify('first day of previous month');
        $assignment = $this->erpNextService->getEmployeeSalaryStructureAssignment(
            $employee['name'], 
            $startDate->format('Y-m-d')
        );
        
        if (!$assignment) {
            // Récupérer les structures salariales disponibles
            $salaryStructures = $this->erpNextService->getSalaryStructures();
            if (empty($salaryStructures)) {
                $result['errors'][] = "Aucune structure salariale disponible pour l'employé {$employee['name']}";
                return $result;
            }
            
            // Utiliser la première structure salariale disponible
            $structureName = $salaryStructures[0]['name'];
            
            try {
                // Assigner la structure salariale
                $this->erpNextService->assignSalaryStructure(
                    $employee['name'],
                    $structureName,
                    $assignmentDate->format('Y-m-d')
                );
                
                // Attendre un peu pour s'assurer que l'assignation est bien enregistrée
                sleep(2);
                
                // Vérifier que l'assignation a bien été effectuée
                $assignment = $this->erpNextService->getEmployeeSalaryStructureAssignment(
                    $employee['name'], 
                    $startDate->format('Y-m-d')
                );
                
                if (!$assignment) {
                    $result['errors'][] = "Impossible d'assigner une structure salariale à l'employé {$employee['name']}";
                    return $result;
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Erreur lors de l'assignation de la structure salariale à l'employé {$employee['name']}: " . $e->getMessage();
                return $result;
            }
        }
        
        // Déterminer le salaire de base selon les spécifications
        $baseAmount = 0;
        $earnings = [];
        $deductions = [];
        
        // 1. Si un salaire de base est spécifié, l'utiliser
        if ($manualSalaryValue !== null && $manualSalaryValue > 0) {
            $baseAmount = $manualSalaryValue;
            $this->logger->info("Using specified base salary", [
                'employee' => $employee['name'],
                'base_salary' => $baseAmount
            ]);
            
            // Même avec un salaire spécifique, récupérer les composants du dernier salaire
            $lastSlipBeforeStart = $this->getLastSalarySlipBeforeDate($employee['name'], $startDate);
            if ($lastSlipBeforeStart) {
                $lastSlipDetails = $this->erpNextService->getSalarySlipDetails($lastSlipBeforeStart['name']);
                if ($lastSlipDetails) {
                    // Copier les composants de gains du dernier salaire
                    if (isset($lastSlipDetails['earnings']) && is_array($lastSlipDetails['earnings'])) {
                        foreach ($lastSlipDetails['earnings'] as $earning) {
                            $earnings[] = [
                                'salary_component' => $earning['salary_component'] ?? '',
                                'amount' => (float)($earning['amount'] ?? 0)
                            ];
                        }
                    }
                    
                    // Copier les composants de déductions du dernier salaire
                    if (isset($lastSlipDetails['deductions']) && is_array($lastSlipDetails['deductions'])) {
                        foreach ($lastSlipDetails['deductions'] as $deduction) {
                            $deductions[] = [
                                'salary_component' => $deduction['salary_component'] ?? '',
                                'amount' => (float)($deduction['amount'] ?? 0)
                            ];
                        }
                    }
                }
            }
        } else {
            // 2. Sinon, récupérer le dernier salaire avant la date de début
            $lastSlipBeforeStart = $this->getLastSalarySlipBeforeDate($employee['name'], $startDate);
            
            if ($lastSlipBeforeStart) {
                $this->logger->info("Found last salary slip before start date", [
                    'employee' => $employee['name'],
                    'slip' => $lastSlipBeforeStart['name'],
                    'slip_date' => $lastSlipBeforeStart['start_date']
                ]);
                
                $lastSlipDetails = $this->erpNextService->getSalarySlipDetails($lastSlipBeforeStart['name']);
                if ($lastSlipDetails) {
                    if ($useAverage) {
                        // Utiliser la moyenne des salaires de base si l'option est cochée
                        $baseAmount = $this->calculateAverageSalary($employee['name'], $startDate);
                        $this->logger->info("Using average base salary", [
                            'employee' => $employee['name'],
                            'average_salary' => $baseAmount
                        ]);
                    } else {
                        // Utiliser le dernier salaire connu
                        $baseAmount = (float)($lastSlipDetails['base'] ?? 0);
                        $this->logger->info("Using last known base salary", [
                            'employee' => $employee['name'],
                            'base_salary' => $baseAmount
                        ]);
                    }
                    
                    // Copier les composants de gains du dernier salaire
                    if (isset($lastSlipDetails['earnings']) && is_array($lastSlipDetails['earnings'])) {
                        foreach ($lastSlipDetails['earnings'] as $earning) {
                            $earnings[] = [
                                'salary_component' => $earning['salary_component'] ?? '',
                                'amount' => (float)($earning['amount'] ?? 0)
                            ];
                        }
                    }
                    
                    // Copier les composants de déductions du dernier salaire
                    if (isset($lastSlipDetails['deductions']) && is_array($lastSlipDetails['deductions'])) {
                        foreach ($lastSlipDetails['deductions'] as $deduction) {
                            $deductions[] = [
                                'salary_component' => $deduction['salary_component'] ?? '',
                                'amount' => (float)($deduction['amount'] ?? 0)
                            ];
                        }
                    }
                }
            } else {
                $this->logger->warning("No previous salary slip found before start date", [
                    'employee' => $employee['name'],
                    'start_date' => $startDate->format('Y-m-d')
                ]);
                
                // Si aucun salaire précédent n'est trouvé, utiliser le salaire de base de l'assignation
                $this->logger->info("Checking salary structure assignment for base amount", [
                    'employee' => $employee['name'],
                    'assignment' => $assignment
                ]);
                
                if (isset($assignment['base']) && $assignment['base'] > 0) {
                    $baseAmount = (float)$assignment['base'];
                    $this->logger->info("Using salary structure assignment base", [
                        'employee' => $employee['name'],
                        'base_salary' => $baseAmount
                    ]);
                } else {
                    // Essayer de récupérer le salaire de base depuis les détails de l'employé
                    $this->logger->info("No base in assignment, trying employee details", [
                        'employee' => $employee['name']
                    ]);
                    
                    try {
                        $employeeDetails = $this->erpNextService->getEmployee($employee['name']);
                        $this->logger->info("Employee details retrieved", [
                            'employee' => $employee['name'],
                            'details' => $employeeDetails
                        ]);
                        
                        if (isset($employeeDetails['salary_rate']) && $employeeDetails['salary_rate'] > 0) {
                            $baseAmount = (float)$employeeDetails['salary_rate'];
                            $this->logger->info("Using employee salary rate", [
                                'employee' => $employee['name'],
                                'base_salary' => $baseAmount
                            ]);
                        } else {
                            // Dernier recours : utiliser un salaire minimum par défaut
                            $baseAmount = 1500.0; // SMIC approximatif
                            $this->logger->warning("Using default minimum salary (no salary_rate found)", [
                                'employee' => $employee['name'],
                                'base_salary' => $baseAmount,
                                'available_fields' => array_keys($employeeDetails ?? [])
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Dernier recours : utiliser un salaire minimum par défaut
                        $baseAmount = 1500.0; // SMIC approximatif
                        $this->logger->warning("Could not get employee details, using default minimum salary", [
                            'employee' => $employee['name'],
                            'base_salary' => $baseAmount,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // Vérifier que nous avons un montant de base valide
        if ($baseAmount <= 0) {
            $this->logger->warning("Invalid base amount for employee", [
                'employee' => $employee['name'],
                'base_amount' => $baseAmount
            ]);
            $result['errors'][] = "Montant de base invalide pour l'employé {$employee['name']}";
            return $result;
        }
        
        $newSlipData = [
            'doctype' => 'Salary Slip',
            'employee' => $employee['name'],
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'posting_date' => $endDate->format('Y-m-d'),
            'company' => $employee['company'],
            'salary_structure' => $assignment['salary_structure'],
            'base' => $baseAmount
        ];
        
        // Ajouter les composants de gains et déductions s'ils existent
        if (!empty($earnings)) {
            $newSlipData['earnings'] = $earnings;
        }
        
        if (!empty($deductions)) {
            $newSlipData['deductions'] = $deductions;
        }

        try {
            $this->logger->info("Creating salary slip", [
                'employee' => $employee['name'],
                'period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
                'structure' => $assignment['salary_structure'],
                'base_amount' => $baseAmount
            ]);
            
            $slipResult = $this->erpNextService->addSalarySlip($newSlipData);
            
            $this->logger->info("Salary slip created successfully", [
                'employee' => $employee['name'],
                'slip' => $slipResult['name'] ?? 'Unknown'
            ]);
            
            $result['created']++;
        } catch (\Throwable $e) {
            $errorMsg = "Failed to create salary slip for employee {$employee['name']}: " . $e->getMessage();
            $this->logger->error($errorMsg, [
                'employee' => $employee['name'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $result['errors'][] = $errorMsg;
        }
        
        return $result;
    }

    /**
     * Récupère la dernière fiche de paie d'un employé avant une date donnée
     */
    private function getLastSalarySlipBeforeDate(string $employeeName, DateTimeInterface $beforeDate): ?array
    {
        try {
            // Récupérer toutes les fiches de paie de l'employé
            $allSlips = $this->erpNextService->getSalarySlipsForEmployee($employeeName);
            
            // Filtrer pour ne garder que les fiches soumises et valides avant la date de début
            $validSlips = array_filter($allSlips, function($slip) use ($beforeDate) {
                if (!isset($slip['docstatus']) || $slip['docstatus'] != 1) {
                    return false;
                }
                
                $slipStartDate = new \DateTime($slip['start_date']);
                return $slipStartDate < $beforeDate;
            });
            
            if (empty($validSlips)) {
                return null;
            }
            
            // Trier par date de début décroissante pour avoir la plus récente en premier
            usort($validSlips, function($a, $b) {
                return strcmp($b['start_date'], $a['start_date']);
            });
            
            return $validSlips[0];
        } catch (\Throwable $e) {
            $this->logger->error("Error retrieving last salary slip before date", [
                'employee' => $employeeName,
                'before_date' => $beforeDate->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Calcule la moyenne des salaires de base des 3 dernières fiches de paie
     */
    private function calculateAverageSalary(string $employeeName, DateTimeInterface $beforeDate): float
    {
        try {
            // Récupérer toutes les fiches de paie de l'employé
            $allSlips = $this->erpNextService->getSalarySlipsForEmployee($employeeName);
            
            // Filtrer pour ne garder que les fiches soumises et valides avant la date de début
            $validSlips = array_filter($allSlips, function($slip) use ($beforeDate) {
                if (!isset($slip['docstatus']) || $slip['docstatus'] != 1) {
                    return false;
                }
                
                $slipStartDate = new \DateTime($slip['start_date']);
                return $slipStartDate < $beforeDate;
            });
            
            if (empty($validSlips)) {
                return 0;
            }
            
            // Trier par date de début décroissante pour avoir les plus récentes en premier
            usort($validSlips, function($a, $b) {
                return strcmp($b['start_date'], $a['start_date']);
            });
            
            // Limiter à 3 fiches maximum pour la moyenne
            $validSlips = array_slice($validSlips, 0, 3);
            
            $totalBase = 0;
            $validCount = 0;
            
            foreach ($validSlips as $slip) {
                $slipDetails = $this->erpNextService->getSalarySlipDetails($slip['name']);
                if ($slipDetails && isset($slipDetails['base'])) {
                    $totalBase += (float)$slipDetails['base'];
                    $validCount++;
                }
            }
            
            return $validCount > 0 ? $totalBase / $validCount : 0;
        } catch (\Throwable $e) {
            $this->logger->error("Error calculating average salary", [
                'employee' => $employeeName,
                'before_date' => $beforeDate->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}