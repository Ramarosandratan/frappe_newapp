<?php

namespace App\Command;

use App\Service\ErpNextImportService;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-with-dependencies',
    description: 'Import data to ERPNext with dependency and document status management',
)]
class ImportWithDependenciesCommand extends Command
{
    public function __construct(
        private readonly ErpNextImportService $importService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('company-file', InputArgument::OPTIONAL, 'Path to company CSV file')
            ->addArgument('employee-file', InputArgument::OPTIONAL, 'Path to employee CSV file')
            ->addArgument('salary-component-file', InputArgument::OPTIONAL, 'Path to salary component CSV file')
            ->addArgument('salary-structure-file', InputArgument::OPTIONAL, 'Path to salary structure CSV file')
            ->addArgument('salary-slip-file', InputArgument::OPTIONAL, 'Path to salary slip CSV file')
            ->addOption('company', null, InputOption::VALUE_REQUIRED, 'Company name to use if no file provided', 'My Company')
            ->addOption('structure', null, InputOption::VALUE_REQUIRED, 'Salary structure name to use', 'gasy1')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('ERPNext Import with Dependencies and Document Status Management');

        try {
            $importData = [
                'company' => $input->getOption('company'),
                'employees' => [],
                'salary_components' => [],
                'salary_structure' => [
                    'name' => $input->getOption('structure'),
                    'company' => $input->getOption('company'),
                    'earnings' => [],
                    'deductions' => [],
                    'payroll_frequency' => 'Monthly'
                ],
                'assignments' => [],
                'salary_slips' => []
            ];

            // Traitement du fichier des entreprises
            $companyFile = $input->getArgument('company-file');
            if ($companyFile && file_exists($companyFile)) {
                $io->section('Processing company file');
                $importData['company'] = $this->processCompanyFile($companyFile);
                $io->success('Company data processed');
            } else {
                $io->note('Using default company: ' . $importData['company']);
            }

            // Traitement du fichier des employés
            $employeeFile = $input->getArgument('employee-file');
            if ($employeeFile && file_exists($employeeFile)) {
                $io->section('Processing employee file');
                $importData['employees'] = $this->processEmployeeFile($employeeFile, $importData['company']);
                $io->success(sprintf('Processed %d employees', count($importData['employees'])));
            }

            // Traitement du fichier des composants salariaux
            $componentFile = $input->getArgument('salary-component-file');
            if ($componentFile && file_exists($componentFile)) {
                $io->section('Processing salary component file');
                $components = $this->processComponentFile($componentFile, $importData['company']);
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
                
                $io->success(sprintf('Processed %d salary components', count($components)));
            }

            // Traitement du fichier des structures salariales
            $structureFile = $input->getArgument('salary-structure-file');
            if ($structureFile && file_exists($structureFile)) {
                $io->section('Processing salary structure file');
                $structureData = $this->processStructureFile($structureFile, $importData['company']);
                $importData['salary_structure'] = array_merge($importData['salary_structure'], $structureData);
                $io->success('Salary structure data processed');
            }

            // Traitement du fichier des bulletins de salaire
            $slipFile = $input->getArgument('salary-slip-file');
            if ($slipFile && file_exists($slipFile)) {
                $io->section('Processing salary slip file');
                $slips = $this->processSlipFile($slipFile, $importData['company'], $importData['salary_structure']['name']);
                $importData['salary_slips'] = $slips;
                
                // Créer automatiquement les assignations de structure pour chaque employé
                foreach ($slips as $slip) {
                    if (!empty($slip['employee'])) {
                        $importData['assignments'][] = [
                            'employee' => $slip['employee'],
                            'salary_structure' => $importData['salary_structure']['name'],
                            'base' => $slip['base'] ?? 0,
                            'from_date' => $slip['start_date'] ?? date('Y-m-01'),
                            'company' => $importData['company']
                        ];
                    }
                }
                
                $io->success(sprintf('Processed %d salary slips', count($slips)));
            }

            // Exécuter l'import
            $io->section('Executing import with dependency management');
            $this->importService->executeImport($importData);
            
            $io->success('Import completed successfully');
            return Command::SUCCESS;
            
        } catch (\Throwable $e) {
            $this->logger->error('Import failed', ['error' => $e->getMessage()]);
            $io->error('Import failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Traite le fichier des entreprises
     */
    private function processCompanyFile(string $filePath): string
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);
        
        $records = $csv->getRecords();
        foreach ($records as $record) {
            // Prendre la première entreprise du fichier
            return $record['company_name'] ?? 'My Company';
        }
        
        return 'My Company';
    }

    /**
     * Traite le fichier des employés
     */
    private function processEmployeeFile(string $filePath, string $company): array
    {
        $csv = Reader::createFromPath($filePath, 'r');
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
     * Traite le fichier des composants salariaux
     */
    private function processComponentFile(string $filePath, string $company): array
    {
        $csv = Reader::createFromPath($filePath, 'r');
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
     * Traite le fichier des structures salariales
     */
    private function processStructureFile(string $filePath, string $company): array
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);
        
        $structure = [
            'company' => $company,
            'payroll_frequency' => 'Monthly'
        ];
        
        $records = $csv->getRecords();
        foreach ($records as $record) {
            // Prendre la première structure du fichier
            if (!empty($record['name'])) {
                $structure['name'] = $record['name'];
            }
            
            if (!empty($record['payroll_frequency'])) {
                $structure['payroll_frequency'] = $record['payroll_frequency'];
            }
            
            if (!empty($record['payment_account'])) {
                $structure['payment_account'] = $record['payment_account'];
            }
            
            // Ne traiter que la première ligne
            break;
        }
        
        return $structure;
    }

    /**
     * Traite le fichier des bulletins de salaire
     */
    private function processSlipFile(string $filePath, string $company, string $structureName): array
    {
        $csv = Reader::createFromPath($filePath, 'r');
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
     * Convertit une date du format français (DD/MM/YYYY) au format ERPNext (YYYY-MM-DD)
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