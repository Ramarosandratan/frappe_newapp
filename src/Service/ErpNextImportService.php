<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service spécialisé pour l'import de données vers ERPNext
 * avec gestion des dépendances et des états des documents
 */
class ErpNextImportService
{
    private array $dependencyMap = [
        'company' => [],
        'employee' => [],
        'component' => [],
        'structure' => []
    ];

    public function __construct(
        private readonly ErpNextService $erpNextService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Exécute l'import complet en respectant les dépendances
     */
    public function executeImport(array $data): void
    {
        $this->logger->info('Starting import process with dependency management');
        
        // Étape 1: Vérifier/créer l'entreprise
        $this->ensureCompanyExists($data['company'] ?? 'My Company');
        
        // Étape 2: Créer les employés
        if (!empty($data['employees'])) {
            foreach ($data['employees'] as $employee) {
                $this->createEmployee($employee);
            }
        }
        
        // Étape 3: Créer les composants salariaux
        if (!empty($data['salary_components'])) {
            foreach ($data['salary_components'] as $component) {
                $this->createSalaryComponent($component);
            }
        }
        
        // Étape 4: Créer et soumettre la structure salariale
        if (!empty($data['salary_structure'])) {
            $structureId = $this->createAndSubmitSalaryStructure($data['salary_structure']);
            
            // Étape 5: Assigner la structure aux employés
            if (!empty($data['assignments'])) {
                foreach ($data['assignments'] as $assignment) {
                    $assignment['salary_structure'] = $structureId;
                    $this->createSalaryAssignment($assignment);
                }
            }
        }
        
        // Étape 6: Créer et soumettre les bulletins de salaire
        if (!empty($data['salary_slips'])) {
            foreach ($data['salary_slips'] as $slip) {
                $this->createAndSubmitSalarySlip($slip);
            }
        }
        
        $this->logger->info('Import process completed successfully');
    }

    /**
     * Vérifie si l'entreprise existe, la crée si nécessaire
     */
    private function ensureCompanyExists(string $companyName): string
    {
        try {
            // Vérifier si l'entreprise existe déjà
            $company = $this->erpNextService->getCompany($companyName);
            
            if ($company) {
                $this->logger->info('Company already exists', ['name' => $companyName]);
                $this->dependencyMap['company'][$companyName] = $companyName;
                return $companyName;
            }
            
            // Créer l'entreprise si elle n'existe pas
            $abbr = $this->generateAbbreviation($companyName);
            $result = $this->erpNextService->createCompany($companyName, $abbr);
            
            if (!isset($result['name'])) {
                throw new \RuntimeException("Failed to create company: $companyName");
            }
            
            $this->logger->info('Company created successfully', ['name' => $companyName]);
            $this->dependencyMap['company'][$companyName] = $result['name'];
            return $result['name'];
            
        } catch (\Throwable $e) {
            $this->logger->error('Error ensuring company exists', [
                'name' => $companyName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Génère une abréviation à partir du nom de l'entreprise
     */
    private function generateAbbreviation(string $name): string
    {
        $words = preg_split('/\s+/', $name);
        $abbr = '';
        
        foreach ($words as $word) {
            if (!empty($word)) {
                $abbr .= strtoupper(substr($word, 0, 1));
            }
        }
        
        return $abbr ?: 'CO';
    }

    /**
     * Crée un employé s'il n'existe pas déjà
     */
    private function createEmployee(array $data): string
    {
        if (empty($data['employee_number'])) {
            throw new \RuntimeException('Employee number is required');
        }
        
        try {
            // Vérifier si l'employé existe déjà
            $existingEmployee = $this->erpNextService->getEmployeeByNumber($data['employee_number']);
            
            if (!empty($existingEmployee)) {
                $employeeId = $existingEmployee[0]['name'];
                $this->logger->info('Employee already exists', [
                    'number' => $data['employee_number'],
                    'id' => $employeeId
                ]);
                $this->dependencyMap['employee'][$data['employee_number']] = $employeeId;
                return $employeeId;
            }
            
            // S'assurer que l'entreprise est spécifiée
            if (empty($data['company'])) {
                $data['company'] = array_values($this->dependencyMap['company'])[0] ?? 'My Company';
            }
            
            // Créer l'employé
            $result = $this->erpNextService->addEmployee($data);
            
            if (!isset($result['name'])) {
                throw new \RuntimeException("Failed to create employee: {$data['employee_number']}");
            }
            
            $this->logger->info('Employee created successfully', [
                'number' => $data['employee_number'],
                'id' => $result['name']
            ]);
            
            $this->dependencyMap['employee'][$data['employee_number']] = $result['name'];
            return $result['name'];
            
        } catch (\Throwable $e) {
            $this->logger->error('Error creating employee', [
                'number' => $data['employee_number'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Crée un composant salarial s'il n'existe pas déjà
     */
    private function createSalaryComponent(array $data): string
    {
        if (empty($data['salary_component'])) {
            throw new \RuntimeException('Salary component name is required');
        }
        
        try {
            // Forcer le nom à être l'abréviation pour référence future
            $abbreviation = $data['name'] ?? $this->generateComponentAbbreviation($data['salary_component']);
            $data['name'] = $abbreviation;
            
            // Vérifier si le composant existe déjà
            $existingComponent = $this->erpNextService->getSalaryComponent($abbreviation);
            
            if ($existingComponent) {
                $this->logger->info('Salary component already exists', [
                    'name' => $abbreviation
                ]);
                $this->dependencyMap['component'][$abbreviation] = $abbreviation;
                return $abbreviation;
            }
            
            // S'assurer que l'entreprise est spécifiée
            if (empty($data['company'])) {
                $data['company'] = array_values($this->dependencyMap['company'])[0] ?? 'My Company';
            }
            
            // Créer le composant
            $result = $this->erpNextService->addSalaryComponent($data);
            
            if (!isset($result['name'])) {
                throw new \RuntimeException("Failed to create salary component: {$data['salary_component']}");
            }
            
            $this->logger->info('Salary component created successfully', [
                'name' => $result['name']
            ]);
            
            $this->dependencyMap['component'][$abbreviation] = $result['name'];
            return $result['name'];
            
        } catch (\Throwable $e) {
            $this->logger->error('Error creating salary component', [
                'component' => $data['salary_component'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Génère une abréviation pour un composant salarial
     */
    private function generateComponentAbbreviation(string $name): string
    {
        $words = preg_split('/\s+/', $name);
        $abbr = '';
        
        foreach ($words as $word) {
            if (!empty($word)) {
                $abbr .= strtoupper(substr($word, 0, 1));
            }
        }
        
        return $abbr ?: 'SC';
    }

    /**
     * Crée et soumet une structure salariale
     */
    private function createAndSubmitSalaryStructure(array $data): string
    {
        if (empty($data['name'])) {
            throw new \RuntimeException('Salary structure name is required');
        }
        
        try {
            $structureName = $data['name'];
            
            // Vérifier si la structure existe déjà
            $existingStructure = $this->erpNextService->getSalaryStructure($structureName);
            
            if ($existingStructure) {
                // Vérifier si elle est déjà soumise
                if (isset($existingStructure['docstatus']) && $existingStructure['docstatus'] == 1) {
                    $this->logger->info('Salary structure already exists and is submitted', [
                        'name' => $structureName
                    ]);
                    $this->dependencyMap['structure'][$structureName] = $structureName;
                    return $structureName;
                }
                
                // Si elle existe mais n'est pas soumise, la soumettre
                $submitResult = $this->erpNextService->submitSalaryStructure($structureName);
                
                if (!isset($submitResult['name']) && !isset($submitResult['already_submitted'])) {
                    throw new \RuntimeException("Failed to submit existing salary structure: $structureName");
                }
                
                $this->logger->info('Existing salary structure submitted successfully', [
                    'name' => $structureName
                ]);
                $this->dependencyMap['structure'][$structureName] = $structureName;
                return $structureName;
            }
            
            // S'assurer que l'entreprise est spécifiée
            if (empty($data['company'])) {
                $data['company'] = array_values($this->dependencyMap['company'])[0] ?? 'My Company';
            }
            
            // Créer la structure
            $result = $this->erpNextService->addSalaryStructure($data);
            
            if (!isset($result['name'])) {
                throw new \RuntimeException("Failed to create salary structure: $structureName");
            }
            
            $this->logger->info('Salary structure created successfully', [
                'name' => $result['name']
            ]);
            
            // Soumettre la structure nouvellement créée
            $submitResult = $this->erpNextService->submitSalaryStructure($result['name']);
            
            if (!isset($submitResult['name']) && !isset($submitResult['already_submitted'])) {
                throw new \RuntimeException("Failed to submit new salary structure: {$result['name']}");
            }
            
            $this->logger->info('New salary structure submitted successfully', [
                'name' => $result['name']
            ]);
            
            $this->dependencyMap['structure'][$structureName] = $result['name'];
            return $result['name'];
            
        } catch (\Throwable $e) {
            $this->logger->error('Error creating/submitting salary structure', [
                'structure' => $data['name'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Crée une assignation de structure salariale
     */
    private function createSalaryAssignment(array $data): string
    {
        if (empty($data['employee']) || empty($data['salary_structure'])) {
            throw new \RuntimeException('Employee and salary structure are required for assignment');
        }
        
        // Vérifier que le montant de base est spécifié
        if (empty($data['base']) || !is_numeric($data['base'])) {
            throw new \RuntimeException('Base amount is required for salary structure assignment');
        }
        
        try {
            // Résoudre l'ID de l'employé si nécessaire
            if (isset($this->dependencyMap['employee'][$data['employee']])) {
                $data['employee'] = $this->dependencyMap['employee'][$data['employee']];
            }
            
            // Résoudre l'ID de la structure si nécessaire
            if (isset($this->dependencyMap['structure'][$data['salary_structure']])) {
                $data['salary_structure'] = $this->dependencyMap['structure'][$data['salary_structure']];
            }
            
            // S'assurer que l'entreprise est spécifiée
            if (empty($data['company'])) {
                $data['company'] = array_values($this->dependencyMap['company'])[0] ?? 'My Company';
            }
            
            // Vérifier si l'assignation existe déjà
            $existingAssignment = $this->erpNextService->getSalaryStructureAssignment(
                $data['employee'],
                $data['salary_structure']
            );
            
            if ($existingAssignment) {
                $this->logger->info('Salary structure assignment already exists', [
                    'employee' => $data['employee'],
                    'structure' => $data['salary_structure']
                ]);
                
                // Vérifier si le montant de base est correct
                if (isset($existingAssignment['base']) && 
                    abs($existingAssignment['base'] - $data['base']) > 0.01) {
                    
                    // Mettre à jour le montant de base si différent
                    $this->logger->info('Updating base amount for existing assignment', [
                        'employee' => $data['employee'],
                        'old_base' => $existingAssignment['base'],
                        'new_base' => $data['base']
                    ]);
                    
                    $existingAssignment['base'] = $data['base'];
                    $updateResult = $this->erpNextService->setDocumentValue(
                        'Salary Structure Assignment',
                        $existingAssignment['name'],
                        'base',
                        $data['base']
                    );
                }
                
                return $existingAssignment['name'];
            }
            
            // Préparer les données pour l'assignation
            $assignmentData = [
                'doctype' => 'Salary Structure Assignment',
                'employee' => $data['employee'],
                'salary_structure' => $data['salary_structure'],
                'from_date' => $data['from_date'] ?? date('Y-m-d'),
                'base' => $data['base'],
                'company' => $data['company']
            ];
            
            if (!empty($data['to_date'])) {
                $assignmentData['to_date'] = $data['to_date'];
            }
            
            // Créer l'assignation directement via l'API
            $result = $this->erpNextService->insertDocument($assignmentData);
            
            if (!isset($result['name'])) {
                throw new \RuntimeException("Failed to create salary structure assignment");
            }
            
            $this->logger->info('Salary structure assignment created successfully', [
                'employee' => $data['employee'],
                'structure' => $data['salary_structure'],
                'base' => $data['base'],
                'id' => $result['name']
            ]);
            
            return $result['name'];
            
        } catch (\Throwable $e) {
            $this->logger->error('Error creating salary structure assignment', [
                'employee' => $data['employee'] ?? 'unknown',
                'structure' => $data['salary_structure'] ?? 'unknown',
                'base' => $data['base'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Crée et soumet un bulletin de salaire
     */
    private function createAndSubmitSalarySlip(array $data): string
    {
        if (empty($data['employee'])) {
            throw new \RuntimeException('Employee is required for salary slip');
        }
        
        try {
            // Résoudre l'ID de l'employé si nécessaire
            if (isset($this->dependencyMap['employee'][$data['employee']])) {
                $data['employee'] = $this->dependencyMap['employee'][$data['employee']];
            }
            
            // Résoudre l'ID de la structure si nécessaire
            if (!empty($data['salary_structure']) && isset($this->dependencyMap['structure'][$data['salary_structure']])) {
                $data['salary_structure'] = $this->dependencyMap['structure'][$data['salary_structure']];
            }
            
            // S'assurer que l'entreprise est spécifiée
            if (empty($data['company'])) {
                $data['company'] = array_values($this->dependencyMap['company'])[0] ?? 'My Company';
            }
            
            // S'assurer que la devise est spécifiée
            if (empty($data['currency'])) {
                $data['currency'] = 'USD'; // Devise par défaut
            }
            
            // Vérifier si le bulletin existe déjà
            $existingSlip = $this->erpNextService->findSalarySlip(
                $data['employee'],
                $data['start_date'] ?? date('Y-m-01'),
                $data['end_date'] ?? date('Y-m-t')
            );
            
            if ($existingSlip) {
                // Vérifier si le bulletin est déjà soumis
                if (isset($existingSlip['docstatus']) && $existingSlip['docstatus'] == 1) {
                    $this->logger->info('Salary slip already exists and is submitted', [
                        'employee' => $data['employee'],
                        'id' => $existingSlip['name']
                    ]);
                    return $existingSlip['name'];
                }
                
                // Vérifier si les montants des composants sont corrects
                $needsUpdate = false;
                
                if (!empty($data['earnings']) && !empty($existingSlip['earnings'])) {
                    // Créer un tableau associatif des montants existants
                    $existingAmounts = [];
                    foreach ($existingSlip['earnings'] as $earning) {
                        if (isset($earning['salary_component']) && isset($earning['amount'])) {
                            $existingAmounts[$earning['salary_component']] = $earning['amount'];
                        }
                    }
                    
                    // Comparer avec les nouveaux montants
                    foreach ($data['earnings'] as $earning) {
                        if (isset($earning['salary_component']) && isset($earning['amount'])) {
                            $component = $earning['salary_component'];
                            $amount = (float)$earning['amount'];
                            
                            if (!isset($existingAmounts[$component]) || 
                                abs($existingAmounts[$component] - $amount) > 0.01) {
                                $needsUpdate = true;
                                break;
                            }
                        }
                    }
                }
                
                // Mettre à jour le bulletin si nécessaire
                if ($needsUpdate) {
                    $this->logger->info('Updating existing salary slip with new amounts', [
                        'employee' => $data['employee'],
                        'id' => $existingSlip['name']
                    ]);
                    
                    // Préparer les données pour la mise à jour
                    $updateData = array_merge($existingSlip, [
                        'doctype' => 'Salary Slip',
                        'earnings' => $data['earnings']
                    ]);
                    
                    // Mettre à jour le bulletin
                    $updateResult = $this->erpNextService->updateSalarySlip($updateData);
                    
                    if (!isset($updateResult['name'])) {
                        throw new \RuntimeException("Failed to update salary slip: {$existingSlip['name']}");
                    }
                    
                    $existingSlip = $updateResult;
                }
                
                // Si le bulletin existe mais n'est pas soumis, le soumettre
                $submitResult = $this->submitDocument('Salary Slip', $existingSlip['name']);
                
                if (!$submitResult) {
                    throw new \RuntimeException("Failed to submit existing salary slip: {$existingSlip['name']}");
                }
                
                $this->logger->info('Existing salary slip submitted successfully', [
                    'id' => $existingSlip['name']
                ]);
                return $existingSlip['name'];
            }
            
            // Préparer les données complètes pour le bulletin
            $slipData = [
                'doctype' => 'Salary Slip',
                'employee' => $data['employee'],
                'employee_name' => $data['employee_name'] ?? '',
                'salary_structure' => $data['salary_structure'],
                'start_date' => $data['start_date'] ?? date('Y-m-01'),
                'end_date' => $data['end_date'] ?? date('Y-m-t'),
                'posting_date' => $data['posting_date'] ?? date('Y-m-d'),
                'company' => $data['company'],
                'currency' => $data['currency'],
                'earnings' => $data['earnings'] ?? []
            ];
            
            // Créer le bulletin directement via l'API
            $result = $this->erpNextService->insertDocument($slipData);
            
            if (!isset($result['name'])) {
                throw new \RuntimeException("Failed to create salary slip");
            }
            
            $this->logger->info('Salary slip created successfully', [
                'employee' => $data['employee'],
                'id' => $result['name'],
                'earnings_count' => count($data['earnings'] ?? [])
            ]);
            
            // Soumettre le bulletin nouvellement créé
            $submitResult = $this->submitDocument('Salary Slip', $result['name']);
            
            if (!$submitResult) {
                throw new \RuntimeException("Failed to submit new salary slip: {$result['name']}");
            }
            
            $this->logger->info('New salary slip submitted successfully', [
                'id' => $result['name']
            ]);
            
            return $result['name'];
            
        } catch (\Throwable $e) {
            $this->logger->error('Error creating/submitting salary slip', [
                'employee' => $data['employee'] ?? 'unknown',
                'earnings' => $data['earnings'] ?? [],
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Méthode générique pour soumettre un document
     */
    private function submitDocument(string $doctype, string $name): bool
    {
        try {
            $result = $this->erpNextService->submitDocument($doctype, $name);
            return isset($result['name']) || isset($result['already_submitted']);
        } catch (\Throwable $e) {
            $this->logger->error('Error in submitDocument', [
                'doctype' => $doctype,
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Soumet plusieurs documents en lot
     */
    public function submitMultipleDocuments(array $documents): bool
    {
        try {
            $docs = [];
            
            foreach ($documents as $doc) {
                if (empty($doc['doctype']) || empty($doc['name'])) {
                    $this->logger->warning('Invalid document for batch submission', ['doc' => $doc]);
                    continue;
                }
                
                $docs[] = [
                    'doctype' => $doc['doctype'],
                    'name' => $doc['name']
                ];
            }
            
            if (empty($docs)) {
                $this->logger->warning('No valid documents for batch submission');
                return false;
            }
            
            $response = $this->erpNextService->submitMultipleDocuments($docs);
            
            $this->logger->info('Batch document submission completed', [
                'count' => count($docs),
                'response' => $response
            ]);
            
            return true;
            
        } catch (\Throwable $e) {
            $this->logger->error('Error in batch document submission', [
                'count' => count($documents),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}