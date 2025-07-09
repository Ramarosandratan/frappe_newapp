<?php

namespace App\Controller;

use App\Form\MultiCsvImportType;
use App\Service\ErpNextService;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImportController extends AbstractController
{
    public function __construct(
        private readonly ErpNextService $erpNextService,
        private readonly LoggerInterface $logger
    ) {}
    
    // Maximum execution time for import process in seconds
    private const IMPORT_TIMEOUT = 300;
    // Maximum number of records to process in a batch
    private const BATCH_LIMIT = 100;

    #[Route('/import', name: 'app_import', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->createForm(MultiCsvImportType::class);
        $form->handleRequest($request);

        $this->logger->info('ImportController index method called.');
        $this->logger->info('Form submitted status', ['isSubmitted' => $form->isSubmitted()]);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->logger->info('Form valid status', ['isValid' => $form->isValid()]);
            // Set a timeout to prevent infinite loops or hanging
            set_time_limit(self::IMPORT_TIMEOUT);
            
            /** @var UploadedFile $employeeFile */
            $employeeFile = $form->get('employee_file')->getData();
            /** @var UploadedFile $structureFile */
            $structureFile = $form->get('structure_file')->getData();
            /** @var UploadedFile $dataFile */
            $dataFile = $form->get('data_file')->getData();

            $this->logger->info('Starting CSV import process', [
                'employee_file' => $employeeFile ? $employeeFile->getClientOriginalName() : 'none',
                'structure_file' => $structureFile ? $structureFile->getClientOriginalName() : 'none',
                'data_file' => $dataFile ? $dataFile->getClientOriginalName() : 'none',
            ]);

            try {
                $startTime = microtime(true);
                $employeeMap = $this->importEmployees($employeeFile);
                $this->logger->info('Employee import completed', ['duration' => microtime(true) - $startTime]);
                
                $startTime = microtime(true);
                $this->importSalaryStructures($structureFile, $employeeMap);
                $this->logger->info('Salary structure import completed', ['duration' => microtime(true) - $startTime]);
                
                $startTime = microtime(true);
                $this->importSalaryData($dataFile, $employeeMap);
                $this->logger->info('Salary data import completed', ['duration' => microtime(true) - $startTime]);
                // No global final success flash; per-stage success provided
            } catch (\Throwable $e) {
                $this->logger->error('An error occurred during CSV import: ' . $e->getMessage(), ['exception' => $e]);
                $this->addFlash('danger', 'An error occurred: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_import');
        }

        return $this->render('import/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param UploadedFile $file
     * @return array Map of employee reference to ERPNext employee name
     */
    private function importEmployees(UploadedFile $file): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $employeeMap = [];
        $successCount = 0;
        $errorCount = 0;
        $companies = [];
        $processedCount = 0;

        $requiredFields = ['Ref', 'Nom', 'Prenom', 'genre', 'Date embauche', 'date naissance', 'company'];
        $headers = $csv->getHeader();
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier employés - Champ requis manquant : $field");
            }
        }

        $records = iterator_to_array($csv->getRecords());
        $this->logger->info('Starting employee import from CSV', ['file' => $file->getClientOriginalName(), 'total_records' => count($records)]);

        foreach ($csv->getRecords() as $index => $record) {
            $processedCount++;
            if ($processedCount > self::BATCH_LIMIT) {
                $this->logger->warning('Batch limit reached for employee import', ['limit' => self::BATCH_LIMIT]);
                $this->addFlash('warning', sprintf('Limite de traitement atteinte. Seuls les %d premiers employés ont été importés.', self::BATCH_LIMIT));
                break;
            }

            try {
                $this->logger->debug('Processing employee record', ['line' => $index + 2, 'ref' => $record['Ref'] ?? 'unknown']);
                foreach ($requiredFields as $field) {
                    if (!isset($record[$field]) || trim($record[$field]) === '') {
                        throw new \RuntimeException("Valeur manquante pour le champ '$field' à la ligne " . ($index + 2));
                    }
                }
                $companyName = trim($record['company']);
                // Ensuring company name is not empty
                if ($companyName === '') {
                    throw new \RuntimeException("Nom de l'entreprise vide à la ligne " . ($index + 2));
                }

                // Normalize case for internal use, avoid mismatch
                $companyNameNormalized = mb_strtolower($companyName);

                $this->ensureCompanyExists($companyName);

                // Save unique companies for holiday list setup
                if (!in_array($companyName, $companies, true)) {
                    $companies[] = $companyName;
                }

                // Check for existing employee
                try {
                    $existingEmployee = $this->erpNextService->getEmployeeByNumber($record['Ref']);
                    if (!empty($existingEmployee)) {
                        $employeeMap[$record['Ref']] = $existingEmployee[0]['name'];
                        $successCount++;
                        $this->logger->debug('Employee already exists', ['ref' => $record['Ref'], 'name' => $existingEmployee[0]['name']]);

                        // Attempt to associate holiday list with existing employee
                        try {
                            $holidayListName = $companyName . " Holidays " . date('Y');
                            $this->erpNextService->setEmployeeHolidayList($existingEmployee[0]['name'], $holidayListName);
                        } catch (\Throwable $e) {
                            $this->logger->warning("Failed to associate holiday list with existing employee", [
                                'employee' => $existingEmployee[0]['name'],
                                'error' => $e->getMessage()
                            ]);
                        }

                        continue;
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning("Error checking for existing employee", [
                        'ref' => $record['Ref'],
                        'error' => $e->getMessage()
                    ]);
                    // Continue to create
                }

                try {
                    $dateOfJoining = $this->convertDate($record['Date embauche']);
                    $dateOfBirth = $this->convertDate($record['date naissance']);
                } catch (\Throwable $e) {
                    throw new \RuntimeException("Erreur de format de date: " . $e->getMessage());
                }

                $employeeData = [
                    'doctype' => 'Employee',
                    'employee_number' => $record['Ref'],
                    'first_name' => $record['Prenom'],
                    'last_name' => $record['Nom'],
                    'employee_name' => $record['Prenom'] . ' ' . $record['Nom'],
                    'gender' => ($record['genre'] === 'Masculin') ? 'Male' : 'Female',
                    'date_of_joining' => $dateOfJoining,
                    'date_of_birth' => $dateOfBirth,
                    'company' => $companyName,
                    'status' => 'Active',
                ];

                $startTime = microtime(true);
                $response = $this->erpNextService->addEmployee($employeeData);
                $this->logger->debug('API call to add employee completed', ['ref' => $record['Ref'], 'duration' => microtime(true) - $startTime]);

                if (isset($response['name'])) {
                    $employeeMap[$record['Ref']] = $response['name'];
                    $successCount++;

                    try {
                        $holidayListName = $companyName . " Holidays " . date('Y');
                        $holidayList = $this->erpNextService->getHolidayList($holidayListName);

                        // Accept both [] and null for empty result
                        if (empty($holidayList)) {
                            $this->erpNextService->createHolidayList($holidayListName, $companyName);
                        }

                        $this->erpNextService->setEmployeeHolidayList($response['name'], $holidayListName);
                    } catch (\Throwable $e) {
                        $this->logger->warning("Failed to associate holiday list with employee", [
                            'employee' => $response['name'],
                            'error' => $e->getMessage()
                        ]);
                        // Do not fail employee import if this fails
                    }
                } else {
                    throw new \RuntimeException("Réponse invalide de l'API ERPNext lors de la création de l'employé");
                }
            } catch (\Throwable $e) {
                $errorCount++;
                $this->logger->error('Failed to import employee', [
                    'ref' => $record['Ref'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                if ($errorCount <= 10) { // Avoid flash flooding
                    $this->addFlash('warning', sprintf(
                        'Échec de l\'importation de l\'employé "%s": %s',
                        $record['Ref'] ?? 'inconnu',
                        $e->getMessage()
                    ));
                }
            }

            // Log progress periodically to detect if the process is stuck
            if ($processedCount % 10 === 0) {
                $this->logger->info('Progress update on employee import', ['processed' => $processedCount, 'successful' => $successCount, 'errors' => $errorCount]);
            }
        }

        // Create default holiday lists for all companies
        foreach ($companies as $companyName) {
            try {
                $holidayListName = $companyName . " Holidays " . date('Y');
                $holidayList = $this->erpNextService->getHolidayList($holidayListName);

                if (empty($holidayList)) {
                    $this->erpNextService->createHolidayList($holidayListName, $companyName);
                }
                $this->erpNextService->setCompanyDefaultHolidayList($companyName, $holidayListName);

            } catch (\Throwable $e) {
                $this->logger->warning("Failed to create/associate holiday list for company", [
                    'company' => $companyName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($successCount > 0) {
            $this->addFlash('success', sprintf('%d employés importés avec succès.', $successCount));
        }

        if ($errorCount > 0) {
            $this->addFlash('danger', sprintf('%d employés n\'ont pas pu être importés (voir logs pour détails).', $errorCount));
        }

        return $employeeMap;
    }

    /**
     * @param UploadedFile $file
     * @param array $employeeMap
     * @return void
     */
    private function importSalaryStructures(UploadedFile $file, array $employeeMap): void
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $structures = [];
        $successCount = 0;
        $errorCount = 0;
        $processedCount = 0;

        $requiredFields = ['salary structure', 'name', 'Abbr', 'type', 'valeur', 'company'];
        $headers = $csv->getHeader();
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier structures salariales - Champ requis manquant : $field");
            }
        }

        $records = iterator_to_array($csv->getRecords());
        $this->logger->info('Starting salary structure import from CSV', ['file' => $file->getClientOriginalName(), 'total_records' => count($records)]);

        // First pass: group components by salary structure
        foreach ($csv->getRecords() as $index => $record) {
            $processedCount++;
            if ($processedCount > self::BATCH_LIMIT) {
                $this->logger->warning('Batch limit reached for salary structure import', ['limit' => self::BATCH_LIMIT]);
                $this->addFlash('warning', sprintf('Limite de traitement atteinte. Seuls les %d premiers composants de structure salariale ont été importés.', self::BATCH_LIMIT));
                break;
            }

            try {
                $this->logger->debug('Processing salary structure record', ['line' => $index + 2, 'structure' => $record['salary structure'] ?? 'unknown']);
                foreach ($requiredFields as $field) {
                    if (!isset($record[$field]) || trim($record[$field]) === '') {
                        throw new \RuntimeException("Valeur manquante pour le champ '$field' à la ligne " . ($index + 2));
                    }
                }
                $companyName = trim($record['company']);
                if ($companyName === '') {
                    throw new \RuntimeException("Nom de l'entreprise vide à la ligne " . ($index + 2));
                }

                $this->ensureCompanyExists($companyName);

                $structures[$record['salary structure']]['components'][] = $record;
                $structures[$record['salary structure']]['company'] = $companyName;
            } catch (\Throwable $e) {
                $errorCount++;
                $this->logger->error('Error processing salary structure component', [
                    'line' => $index + 2,
                    'error' => $e->getMessage()
                ]);
                if ($errorCount <= 10) {
                    $this->addFlash('warning', sprintf(
                        'Erreur lors du traitement du composant à la ligne %d: %s',
                        $index + 2,
                        $e->getMessage()
                    ));
                }
            }

            // Log progress periodically to detect if the process is stuck
            if ($processedCount % 10 === 0) {
                $this->logger->info('Progress update on salary structure import', ['processed' => $processedCount, 'errors' => $errorCount]);
            }
        }

        foreach ($structures as $name => $data) {
            try {
                $componentSuccessCount = 0;
                $componentErrorCount = 0;
                foreach ($data['components'] as $component) {
                    try {
                        $isFormulaBased = (strtolower(trim($component['valeur'])) !== 'base');
                        $componentData = [
                            'doctype' => 'Salary Component',
                            'salary_component' => $component['name'],
                            'abbr' => $component['Abbr'],
                            'type' => ucfirst($component['type']),
                            'formula' => $isFormulaBased ? $component['valeur'] : null,
                            'amount_based_on_formula' => $isFormulaBased ? 1 : 0,
                            'depends_on_payment_days' => $isFormulaBased ? 0 : 1,
                            'company' => $component['company'],
                        ];
                        $this->erpNextService->saveSalaryComponent($componentData);
                        $componentSuccessCount++;
                    } catch (\Throwable $e) {
                        $componentErrorCount++;
                        $this->logger->warning('Component creation/update failed', [
                            'component' => $component['name'],
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }

                $earnings = [];
                $deductions = [];
                foreach ($data['components'] as $component) {
                    $item = [
                        'salary_component' => $component['name'],
                        'formula' => (strtolower(trim($component['valeur'])) !== 'base') ? $component['valeur'] : null,
                        'amount_based_on_formula' => (strtolower(trim($component['valeur'])) !== 'base') ? 1 : 0,
                        'depends_on_payment_days' => (strtolower(trim($component['valeur'])) !== 'base') ? 0 : 1,
                    ];

                    if (strtolower($component['type']) === 'earning') {
                        $earnings[] = $item;
                    } else {
                        $deductions[] = $item;
                    }
                }
                $structureData = [
                    'doctype' => 'Salary Structure',
                    'name' => $name,
                    'company' => $data['company'],
                    'earnings' => $earnings,
                    'deductions' => $deductions,
                    'is_active' => 'Yes',
                    'payment_account' => null,
                ];

                $this->erpNextService->saveSalaryStructure($structureData);

                if (!empty($employeeMap)) {
                    foreach ($employeeMap as $employeeNumber => $employeeId) {
                        try {
                            $fromDate = date('Y-m-01');
                            $this->erpNextService->assignSalaryStructure($employeeId, $name, $fromDate);
                        } catch (\Throwable $e) {
                            $this->logger->warning("Failed to assign salary structure to employee", [
                                'employee' => $employeeId,
                                'structure' => $name,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }

                $successCount++;
                $this->addFlash('success', sprintf('Structure salariale "%s" importée avec succès.', $name));
            } catch (\Throwable $e) {
                $errorCount++;
                $this->logger->error('Salary structure import failed', [
                    'structure' => $name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                if ($errorCount <= 10) {
                    $this->addFlash('warning', sprintf(
                        'Échec de l\'importation de la structure salariale "%s": %s',
                        $name,
                        $e->getMessage()
                    ));
                }
            }
        }

        if ($successCount > 0) {
            $this->addFlash('success', sprintf('%d structures salariales importées avec succès.', $successCount));
        }

        if ($errorCount > 0) {
            $this->addFlash('danger', sprintf('%d structures salariales n\'ont pas pu être importées. Consultez les logs pour le détail.', $errorCount));
        }
    }

    /**
     * @param UploadedFile $file
     * @param array $employeeMap
     * @return void
     */
    private function importSalaryData(UploadedFile $file, array $employeeMap): void
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $successCount = 0;
        $errorCount = 0;
        $processedCount = 0;

        $requiredFields = ['Mois', 'Ref Employe', 'Salaire Base', 'Salaire'];
        $headers = $csv->getHeader();
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier données salariales - Champ requis manquant : $field");
            }
        }

        $records = iterator_to_array($csv->getRecords());
        $this->logger->info('Starting salary data import from CSV', ['file' => $file->getClientOriginalName(), 'total_records' => count($records)]);

        foreach ($csv->getRecords() as $index => $record) {
            $processedCount++;
            if ($processedCount > self::BATCH_LIMIT) {
                $this->logger->warning('Batch limit reached for salary data import', ['limit' => self::BATCH_LIMIT]);
                $this->addFlash('warning', sprintf('Limite de traitement atteinte. Seuls les %d premiers enregistrements de salaire ont été importés.', self::BATCH_LIMIT));
                break;
            }

            try {
                $this->logger->debug('Processing salary data record', ['line' => $index + 2, 'employee_ref' => $record['Ref Employe'] ?? 'unknown']);
                foreach ($requiredFields as $field) {
                    if (!isset($record[$field]) || trim($record[$field]) === '') {
                        throw new \RuntimeException("Valeur manquante pour le champ '$field' à la ligne " . ($index + 2));
                    }
                }

                $employeeRef = $record['Ref Employe'];

                if (!isset($employeeMap[$employeeRef])) {
                    $existingEmployee = $this->erpNextService->getEmployeeByNumber($employeeRef);
                    if (!empty($existingEmployee)) {
                        $employeeId = $existingEmployee[0]['name'];
                        $employeeMap[$employeeRef] = $employeeId;
                    } else {
                        throw new \Exception("Employé avec référence '$employeeRef' introuvable");
                    }
                } else {
                    $employeeId = $employeeMap[$employeeRef];
                }

                // Validate salary structure existence using actual "name"
                $structureName = $record['Salaire'];
                $salaryStructure = $this->erpNextService->getSalaryStructure($structureName);
                if (empty($salaryStructure)) {
                    throw new \RuntimeException("Structure salariale '$structureName' introuvable");
                }

                // Date parsing: try d/m/Y then m/Y (first day of month)
                try {
                    $startDate = \DateTime::createFromFormat('d/m/Y', $record['Mois']);
                    if (!$startDate) {
                        $startDate = \DateTime::createFromFormat('m/Y', $record['Mois']);
                        if (!$startDate) {
                            throw new \Exception("Format de date invalide");
                        }
                        $year = (int)$startDate->format('Y');
                        $month = (int)$startDate->format('m');
                        $startDate->setDate($year, $month, 1);
                    }
                    $endDate = (clone $startDate)->modify('last day of this month');
                } catch (\Throwable $e) {
                    throw new \RuntimeException("Format de date invalide pour '{$record['Mois']}': " . $e->getMessage());
                }

                // Assigner la structure salariale avec une date antérieure à la période de paie
                // Utiliser le premier jour du mois précédent pour s'assurer que la structure est applicable
                $assignmentDate = (clone $startDate)->modify('first day of previous month');
                
                $this->logger->info("Assigning salary structure to employee", [
                    'employee' => $employeeId,
                    'structure' => $structureName,
                    'from_date' => $assignmentDate->format('Y-m-d'),
                    'salary_period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')
                ]);
                
                try {
                    // Assigner la structure salariale
                    $startTime = microtime(true);
                    $this->erpNextService->assignSalaryStructure(
                        $employeeId,
                        $structureName,
                        $assignmentDate->format('Y-m-d')
                    );
                    $this->logger->debug('API call to assign salary structure completed', ['employee' => $employeeId, 'duration' => microtime(true) - $startTime]);
                    
                    // Attendre un peu pour s'assurer que l'assignation est bien enregistrée
                    sleep(2);
                    
                    // Vérifier que l'assignation a bien été effectuée
                    $assignment = $this->erpNextService->getEmployeeSalaryStructureAssignment(
                        $employeeId, 
                        $startDate->format('Y-m-d')
                    );
                    
                    if (!$assignment) {
                        throw new \RuntimeException("L'assignation de la structure salariale n'a pas été correctement enregistrée");
                    }
                    
                    $this->logger->info("Salary structure assigned successfully", [
                        'employee' => $employeeId,
                        'structure' => $structureName,
                        'assignment' => $assignment['name']
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->warning("Failed to assign salary structure, but continuing", [
                        'employee' => $employeeId,
                        'structure' => $structureName,
                        'error' => $e->getMessage()
                    ]);
                }

                $salaryData = [
                    'employee' => $employeeId,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'posting_date' => date('Y-m-d'),
                    'salary_structure' => $structureName,
                    'base' => $this->parseSalaryAmount($record['Salaire Base'])
                ];

                $startTime = microtime(true);
                $this->erpNextService->addSalarySlip($salaryData);
                $this->logger->debug('API call to add salary slip completed', ['employee' => $employeeId, 'duration' => microtime(true) - $startTime]);
                $successCount++;
            } catch (\Throwable $e) {
                $errorCount++;
                $this->logger->error('Salary slip import failed: ' . $e->getMessage(), [
                    'employee_ref' => $record['Ref Employe'] ?? 'unknown',
                    'month' => $record['Mois'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                if ($errorCount <= 10) {
                    $this->addFlash('warning', sprintf(
                        'Échec de l\'importation de la fiche de paie pour l\'employé "%s" (mois %s): %s',
                        $record['Ref Employe'] ?? 'inconnu',
                        $record['Mois'] ?? 'inconnu',
                        $e->getMessage()
                    ));
                }
            }

            // Log progress periodically to detect if the process is stuck
            if ($processedCount % 10 === 0) {
                $this->logger->info('Progress update on salary data import', ['processed' => $processedCount, 'successful' => $successCount, 'errors' => $errorCount]);
            }
        }

        if ($successCount > 0) {
            $this->addFlash('success', sprintf('%d fiches de paie importées avec succès.', $successCount));
        }
        if ($errorCount > 0) {
            $this->addFlash('danger', sprintf('%d fiches de paie n\'ont pas pu être importées. Consultez les logs pour détails.', $errorCount));
        }
    }

    /**
     * @param string $date Format d/m/Y, returns Y-m-d
     * @return string
     */
    private function convertDate(string $date): string
    {
        $d = \DateTime::createFromFormat('d/m/Y', $date);
        if (!$d || $d->format('d/m/Y') !== $date) {
            throw new \InvalidArgumentException("Format de date invalide : $date (attendu: JJ/MM/AAAA)");
        }
        return $d->format('Y-m-d');
    }

    /**
     * Ensures company exists in ERPNext. Throws on error.
     * @param string $companyName
     * @return void
     */
    private function ensureCompanyExists(string $companyName): void
    {
        $companyName = trim($companyName);
        if ($companyName === '') {
            throw new \RuntimeException("Nom d'entreprise vide !");
        }
        $this->logger->info("Checking if company exists", ['company' => $companyName]);
        $company = $this->erpNextService->getCompany($companyName);
        if ($company) {
            return;
        }
        // Build abbreviation
        $abbr = '';
        $words = explode(' ', $companyName);
        foreach ($words as $word) {
            if (!empty($word)) {
                $abbr .= strtoupper(mb_substr($word, 0, 1));
            }
        }
        // If still too short use first 3 letters
        if (strlen($abbr) < 2) {
            $abbr = strtoupper(substr($companyName, 0, 3));
        }
        if ($abbr === '') {
            throw new \RuntimeException('Impossible de déterminer une abréviation pour la société "' . $companyName . '"');
        }
        try {
            $createdCompany = $this->erpNextService->createCompany($companyName, $abbr);
            if (!isset($createdCompany['name'])) {
                throw new \RuntimeException("La création de l'entreprise a échoué - réponse invalide de l'API");
            }
            // Wait so the ERPNext instance indexes the company, try a few times
            $maxAttempts = 3;
            $attempt = 0;
            $companyAvailable = false;
            while ($attempt < $maxAttempts && !$companyAvailable) {
                $checkCompany = $this->erpNextService->getCompany($companyName);
                if ($checkCompany) {
                    $companyAvailable = true;
                } else {
                    sleep(2);
                }
                $attempt++;
            }
            if (!$companyAvailable) {
                // Log, don't fail
                $this->logger->warning("Company was created but is not yet available in the system", [
                    'company' => $companyName
                ]);
            }
            $this->addFlash('success', sprintf('Entreprise "%s" créée avec succès', $companyName));
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'DuplicateEntryError')
                || str_contains($e->getMessage(), 'already exists')) {
                return;
            }
            throw new \RuntimeException("Échec de la création de l'entreprise '$companyName': " . $e->getMessage());
        }
    }

    /**
     * Parse salary amounts in various number formats robustly
     * @param string $amount
     * @return float
     */
    private function parseSalaryAmount(string $amount): float
    {
        // Remove all non-digit except decimals (works for "1 234,56" and "1,234.56" etc)
        // Try FR style: "1 234,56" becomes "1234.56"
        $clean = str_replace([' ', "\xA0", "\u00A0"], ['', '', ''], $amount); // no-break spaces!
        $clean = str_replace(',', '.', $clean); // replace decimal , with .
        // Remove all but . and digits (thousands grouped by non-breaking/space/comma already handled)
        $clean = preg_replace('/[^\d\.]/', '', $clean);

        if (!is_numeric($clean)) {
            throw new \InvalidArgumentException("Montant salarial invalide : $amount");
        }
        return (float)$clean;
    }

    /**
     * Evaluate math formulas safely (allowing identifiers like "SB")
     * @param string $formula
     * @return float
     */
    private function safeEval(string $formula): float
    {
        // Remove whitespace, allow variables (alpha), numbers and operators
        $formula = preg_replace('/\s+/', '', $formula);
        if (!preg_match('/^[a-zA-Z0-9+\-*\/().%]+$/', $formula)) {
            throw new \InvalidArgumentException("Invalid characters in formula: $formula");
        }
        try {
            $parser = new \MathParser\StdMathParser();
            $AST = $parser->parse($formula);
            // If variables needed, you must predefine them as constants here
            // $evaluator = new \MathParser\Interpreting\Evaluator();
            // $evaluator->setVariables(['SB' => 1000, ...]);
            $evaluator = new \MathParser\Interpreting\Evaluator();
            $result = $AST->accept($evaluator);

            if (!is_numeric($result)) {
                throw new \InvalidArgumentException("Formula did not return a number: $formula");
            }
            return (float)$result;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("Invalid formula syntax: $formula - " . $e->getMessage());
        }
    }
}
