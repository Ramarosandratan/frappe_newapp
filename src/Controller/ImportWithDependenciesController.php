<?php

namespace App\Controller;

/**
 * Contrôleur pour l'importation avec dépendances entre les données.
 *
 * Gère l'importation coordonnée de :
 * - Employés
 * - Composants salariaux
 * - Structures salariales
 * - Bulletins de paie
 *
 * Assure la cohérence des données importées en gérant les relations entre elles.
 */
use App\Service\ErpNextImportService;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur pour l'importation des données avec dépendances (employés, composants salariaux et bulletins).
 * Gère l'importation coordonnée de plusieurs types de données liées entre elles.
 */
class ImportWithDependenciesController extends AbstractController
{
    public function __construct(
        private readonly ErpNextImportService $importService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/import/with-dependencies', name: 'app_import_with_dependencies')]
    /**
     * Affiche et traite le formulaire d'importation avec dépendances.
     *
     * @param Request $request L'objet requête HTTP contenant les fichiers uploadés
     * @return Response La réponse HTTP rendant la vue ou redirigeant
     * @throws \Throwable En cas d'erreur lors du traitement des fichiers
     */
    public function index(Request $request): Response
    {
        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            try {
                $companyName = $request->request->get('company', 'My Company');
                $structureName = $request->request->get('structure', 'gasy1');
                
                $importData = [
                    'company' => $companyName,
                    'employees' => [],
                    'salary_components' => [],
                    'salary_structure' => [
                        'name' => $structureName,
                        'company' => $companyName,
                        'earnings' => [],
                        'deductions' => [],
                        'payroll_frequency' => 'Monthly'
                    ],
                    'assignments' => [],
                    'salary_slips' => []
                ];

                // Traitement du fichier des employés
                $employeeFile = $request->files->get('employee_file');
                if ($employeeFile instanceof UploadedFile) {
                    $importData['employees'] = $this->processEmployeeFile($employeeFile, $companyName);
                }

                // Traitement du fichier des composants salariaux
                $componentFile = $request->files->get('component_file');
                if ($componentFile instanceof UploadedFile) {
                    $components = $this->processComponentFile($componentFile, $companyName);
                    $importData['salary_components'] = $components;
                    
                    // Ajouter les composants à la structure salariale
                    foreach ($components as $component) {
                        if ($component['type'] === 'Earning') {
                            $importData['salary_structure']['earnings'][] = [
                                'salary_component' => $component['name']
                            ];
                        } elseif ($component['type'] === 'Deduction') {
                            $importData['salary_structure']['deductions'][] = [
                                'salary_component' => $component['name']
                            ];
                        }
                    }
                }

                // Traitement du fichier des bulletins de salaire
                $slipFile = $request->files->get('slip_file');
                if ($slipFile instanceof UploadedFile) {
                    $slips = $this->processSlipFile($slipFile, $companyName, $structureName);
                    $importData['salary_slips'] = $slips;
                    
                    // Créer automatiquement les assignations de structure pour chaque employé
                    foreach ($slips as $slip) {
                        if (!empty($slip['employee'])) {
                            $importData['assignments'][] = [
                                'employee' => $slip['employee'],
                                'salary_structure' => $structureName,
                                'base' => $slip['base'] ?? 0,
                                'from_date' => $slip['start_date'] ?? date('Y-m-01'),
                                'company' => $companyName
                            ];
                        }
                    }
                }

                // Exécuter l'import
                $this->importService->executeImport($importData);
                
                $success = 'Import completed successfully';
                
            } catch (\Throwable $e) {
                $this->logger->error('Import failed', ['error' => $e->getMessage()]);
                $error = 'Import failed: ' . $e->getMessage();
            }
        }

        return $this->render('import/with_dependencies.html.twig', [
            'error' => $error,
            'success' => $success
        ]);
    }

    /**
     * Traite le fichier CSV des employés et extrait les données pour l'importation.
     *
     * @param UploadedFile $file Fichier CSV uploadé contenant les données des employés
     * @param string $company Nom de l'entreprise pour lier les employés
     * @return array Tableau des données d'employés formatées
     * @throws \RuntimeException Si le fichier est invalide ou les données incomplètes
     */
    /**
     * Traite le fichier CSV des employés et prépare les données pour l'importation.
     *
     * @param UploadedFile $file Fichier CSV uploadé contenant les données des employés
     * @param string $company Nom de l'entreprise pour lier les employés
     * @return array Tableau des données d'employés préparées pour l'importation
     * @throws \RuntimeException Si le fichier est invalide ou les données incomplètes
     */
    private function processEmployeeFile(UploadedFile $file, string $company): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        
        $employees = [];
        $records = $csv->getRecords();
        
        foreach ($records as $record) {
            if (empty($record['employee_number'])) {
                continue;
            }
            
            $employee = [
                'employee_number' => $record['employee_number'],
                'first_name' => $record['first_name'] ?? '',
                'last_name' => $record['last_name'] ?? '',
                'gender' => $record['gender'] ?? 'Male',
                'company' => $company,
                'status' => 'Active'
            ];
            
            // Convertir les dates si présentes
            if (!empty($record['date_of_joining'])) {
                $employee['date_of_joining'] = $this->formatDate($record['date_of_joining']);
            }
            
            if (!empty($record['date_of_birth'])) {
                $employee['date_of_birth'] = $this->formatDate($record['date_of_birth']);
            }
            
            $employees[] = $employee;
        }
        
        return $employees;
    }

    /**
     * Traite le fichier CSV des composants salariaux et prépare les données pour l'importation.
     *
     * @param UploadedFile $file Fichier CSV uploadé contenant les composants salariaux
     * @param string $company Nom de l'entreprise pour lier les composants
     * @return array Tableau des composants salariaux préparés pour l'importation
     * @throws \RuntimeException Si le fichier est invalide ou les données incomplètes
     */
    private function processComponentFile(UploadedFile $file, string $company): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        
        $components = [];
        $records = $csv->getRecords();
        
        foreach ($records as $record) {
            if (empty($record['salary_component'])) {
                continue;
            }
            
            $component = [
                'salary_component' => $record['salary_component'],
                'name' => $record['abbreviation'] ?? null,
                'type' => $record['type'] ?? 'Earning',
                'company' => $company
            ];
            
            if (!empty($record['formula'])) {
                $component['formula'] = $record['formula'];
            }
            
            $components[] = $component;
        }
        
        return $components;
    }

    /**
     * Traite le fichier CSV des bulletins de salaire et extrait les données pour l'importation.
     *
     * @param UploadedFile $file Fichier CSV uploadé contenant les bulletins
     * @param string $company Nom de l'entreprise pour lier les bulletins
     * @param string $structureName Nom de la structure salariale à utiliser
     * @return array Tableau des bulletins formatés
     * @throws \RuntimeException Si le fichier est invalide ou les données incomplètes
     */
    /**
     * Traite le fichier CSV des bulletins de salaire et prépare les données pour l'importation.
     *
     * @param UploadedFile $file Fichier CSV uploadé contenant les bulletins de salaire
     * @param string $company Nom de l'entreprise pour lier les bulletins
     * @param string $structureName Nom de la structure salariale à utiliser
     * @return array Tableau des bulletins préparés pour l'importation
     * @throws \RuntimeException Si le fichier est invalide ou les données incomplètes
     */
    private function processSlipFile(UploadedFile $file, string $company, string $structureName): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        
        $slips = [];
        $records = $csv->getRecords();
        
        foreach ($records as $record) {
            if (empty($record['employee_number'])) {
                continue;
            }
            
            $startDate = !empty($record['start_date']) ? $this->formatDate($record['start_date']) : date('Y-m-01');
            $endDate = !empty($record['end_date']) ? $this->formatDate($record['end_date']) : date('Y-m-t');
            
            $slip = [
                'employee' => $record['employee_number'],
                'employee_name' => $record['employee_name'] ?? '',
                'salary_structure' => $structureName,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'posting_date' => $endDate,
                'company' => $company,
                'currency' => $record['currency'] ?? 'USD',
                'earnings' => []
            ];
            
            // Ajouter les composants de salaire
            foreach ($record as $key => $value) {
                if (strpos($key, 'component_') === 0 && !empty($value)) {
                    $componentName = str_replace('component_', '', $key);
                    $slip['earnings'][] = [
                        'salary_component' => $componentName,
                        'amount' => (float) $value
                    ];
                    
                    // Stocker également le montant de base pour l'assignation
                    if ($componentName === 'SB') {
                        $slip['base'] = (float) $value;
                    }
                }
            }
            
            $slips[] = $slip;
        }
        
        return $slips;
    }

    /**
     * Convertit une date dans différents formats vers le format YYYY-MM-DD.
     *
     * @param string $date Date à convertir (supporte DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD)
     * @return string Date au format YYYY-MM-DD
     * @throws \InvalidArgumentException Si le format de date n'est pas reconnu
     */
    /**
     * Convertit une date dans différents formats vers le format YYYY-MM-DD attendu par ERPNext.
     *
     * @param string $date Date à convertir (supporte DD/MM/YYYY, DD-MM-YYYY et YYYY-MM-DD)
     * @return string Date au format YYYY-MM-DD
     * @throws \InvalidArgumentException Si le format de date n'est pas reconnu
     */
    private function formatDate(string $date): string
    {
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            $dateObj = \DateTime::createFromFormat('d/m/Y', $date);
            return $dateObj->format('Y-m-d');
        }
        
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
            $dateObj = \DateTime::createFromFormat('d-m-Y', $date);
            return $dateObj->format('Y-m-d');
        }
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date; // Déjà au bon format
        }
        
        // Format par défaut si non reconnu
        return date('Y-m-d');
    }
}