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
     * Génère les fiches de paie pour tous les employés actifs pour la période spécifiée
     * 
     * @param DateTimeInterface $startDate Date de début de la période
     * @param DateTimeInterface $endDate Date de fin de la période
     * @param bool $overwrite Écraser les fiches existantes
     * @param bool $useAverage Utiliser la moyenne des fiches précédentes
     * @return array{created: int, skipped: int, errors: array}
     */
    public function generate(DateTimeInterface $startDate, DateTimeInterface $endDate, bool $overwrite, bool $useAverage): array
    {
        $this->logger->info("Starting salary slip generation", [
            'period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            'overwrite' => $overwrite ? 'yes' : 'no',
            'use_average' => $useAverage ? 'yes' : 'no'
        ]);
        
        try {
            $employees = $this->erpNextService->getEmployees(['status' => 'Active']);
            $this->logger->info("Found active employees", ['count' => count($employees)]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to retrieve employees", ['error' => $e->getMessage()]);
            return ['created' => 0, 'skipped' => 0, 'errors' => ["Failed to retrieve employees: " . $e->getMessage()]];
        }
        
        $summary = ['created' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($employees as $employee) {
            $this->logger->info("Processing employee", [
                'employee' => $employee['name'],
                'employee_name' => $employee['employee_name'] ?? 'Unknown'
            ]);
            
            try {
                $existingSlips = $this->erpNextService->getSalarySlips([
                    'employee' => $employee['name'],
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ]);

                if (!empty($existingSlips) && !$overwrite) {
                    $this->logger->info("Skipping employee - salary slip already exists", [
                        'employee' => $employee['name'],
                        'slip' => $existingSlips[0]['name'] ?? 'Unknown'
                    ]);
                    $summary['skipped']++;
                    continue;
                }
                
                if (!empty($existingSlips) && $overwrite) {
                    $this->logger->info("Existing salary slip will be overwritten", [
                        'employee' => $employee['name'],
                        'slip' => $existingSlips[0]['name'] ?? 'Unknown'
                    ]);
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
                    $summary['errors'][] = "Aucune structure salariale disponible pour l'employé {$employee['name']}";
                    continue;
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
                        $summary['errors'][] = "Impossible d'assigner une structure salariale à l'employé {$employee['name']}";
                        continue;
                    }
                } catch (\Exception $e) {
                    $summary['errors'][] = "Erreur lors de l'assignation de la structure salariale à l'employé {$employee['name']}: " . $e->getMessage();
                    continue;
                }
            }
            
            // Récupérer les fiches de paie précédentes pour calculer la moyenne si nécessaire
            $baseAmount = 0;
            $earnings = [];
            $deductions = [];
            
            if ($useAverage) {
                // Récupérer les 3 dernières fiches de paie pour calculer une moyenne
                $previousSlips = $this->erpNextService->getSalarySlipsForEmployee($employee['name']);
                
                // Filtrer pour ne garder que les fiches soumises et valides
                $validSlips = array_filter($previousSlips, function($slip) {
                    return isset($slip['docstatus']) && $slip['docstatus'] == 1;
                });
                
                // Limiter à 3 fiches maximum pour la moyenne
                $validSlips = array_slice($validSlips, 0, 3);
                
                if (count($validSlips) > 0) {
                    $this->logger->info("Calculating average from previous salary slips", [
                        'employee' => $employee['name'],
                        'slips_count' => count($validSlips)
                    ]);
                    
                    // Récupérer les détails complets de chaque fiche
                    $slipDetails = [];
                    foreach ($validSlips as $slip) {
                        $details = $this->erpNextService->getSalarySlipDetails($slip['name']);
                        if ($details) {
                            $slipDetails[] = $details;
                        }
                    }
                    
                    // Calculer la moyenne des montants de base
                    $totalBase = 0;
                    foreach ($slipDetails as $details) {
                        $totalBase += (float)($details['base'] ?? 0);
                    }
                    
                    if (count($slipDetails) > 0) {
                        $baseAmount = $totalBase / count($slipDetails);
                        
                        // Collecter et moyenner les composants de gains
                        $earningsComponents = [];
                        foreach ($slipDetails as $details) {
                            if (isset($details['earnings']) && is_array($details['earnings'])) {
                                foreach ($details['earnings'] as $earning) {
                                    $componentName = $earning['salary_component'] ?? '';
                                    if ($componentName) {
                                        if (!isset($earningsComponents[$componentName])) {
                                            $earningsComponents[$componentName] = [
                                                'total' => 0,
                                                'count' => 0
                                            ];
                                        }
                                        $earningsComponents[$componentName]['total'] += (float)($earning['amount'] ?? 0);
                                        $earningsComponents[$componentName]['count']++;
                                    }
                                }
                            }
                        }
                        
                        // Calculer la moyenne pour chaque composant de gains
                        foreach ($earningsComponents as $componentName => $data) {
                            if ($data['count'] > 0) {
                                $earnings[] = [
                                    'salary_component' => $componentName,
                                    'amount' => $data['total'] / $data['count']
                                ];
                            }
                        }
                        
                        // Collecter et moyenner les composants de déductions
                        $deductionsComponents = [];
                        foreach ($slipDetails as $details) {
                            if (isset($details['deductions']) && is_array($details['deductions'])) {
                                foreach ($details['deductions'] as $deduction) {
                                    $componentName = $deduction['salary_component'] ?? '';
                                    if ($componentName) {
                                        if (!isset($deductionsComponents[$componentName])) {
                                            $deductionsComponents[$componentName] = [
                                                'total' => 0,
                                                'count' => 0
                                            ];
                                        }
                                        $deductionsComponents[$componentName]['total'] += (float)($deduction['amount'] ?? 0);
                                        $deductionsComponents[$componentName]['count']++;
                                    }
                                }
                            }
                        }
                        
                        // Calculer la moyenne pour chaque composant de déductions
                        foreach ($deductionsComponents as $componentName => $data) {
                            if ($data['count'] > 0) {
                                $deductions[] = [
                                    'salary_component' => $componentName,
                                    'amount' => $data['total'] / $data['count']
                                ];
                            }
                        }
                    }
                } else {
                    $this->logger->warning("No previous salary slips found for average calculation", [
                        'employee' => $employee['name']
                    ]);
                }
            } else {
                // Si on n'utilise pas la moyenne, on peut récupérer la dernière fiche de paie comme base
                $lastSlip = $this->erpNextService->getSalarySlipsForEmployee($employee['name']);
                if (!empty($lastSlip)) {
                    $lastSlipDetails = $this->erpNextService->getSalarySlipDetails($lastSlip[0]['name']);
                    if ($lastSlipDetails) {
                        $baseAmount = (float)($lastSlipDetails['base'] ?? 0);
                        
                        // Copier les composants de gains
                        if (isset($lastSlipDetails['earnings']) && is_array($lastSlipDetails['earnings'])) {
                            foreach ($lastSlipDetails['earnings'] as $earning) {
                                $earnings[] = [
                                    'salary_component' => $earning['salary_component'] ?? '',
                                    'amount' => (float)($earning['amount'] ?? 0)
                                ];
                            }
                        }
                        
                        // Copier les composants de déductions
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
                
                $result = $this->erpNextService->addSalarySlip($newSlipData);
                
                $this->logger->info("Salary slip created successfully", [
                    'employee' => $employee['name'],
                    'slip' => $result['name'] ?? 'Unknown'
                ]);
                
                $summary['created']++;
            } catch (\Throwable $e) {
                $errorMsg = "Failed to create salary slip for employee {$employee['name']}: " . $e->getMessage();
                $this->logger->error($errorMsg, [
                    'employee' => $employee['name'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $summary['errors'][] = $errorMsg;
            }
        }
        
        $this->logger->info("Salary slip generation completed", [
            'created' => $summary['created'],
            'skipped' => $summary['skipped'],
            'errors' => count($summary['errors'])
        ]);

        return $summary;
    }
}