<?php

namespace App\Command;

use App\Service\ErpNextService;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-salary-slips',
    description: 'Import salary slips from CSV files'
)]
class ImportSalarySlipsCommand extends Command
{
    public function __construct(
        private readonly ErpNextService $erpNextService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('employees_file', InputArgument::REQUIRED, 'Path to employees CSV file')
            ->addArgument('structures_file', InputArgument::REQUIRED, 'Path to salary structures CSV file')
            ->addArgument('data_file', InputArgument::REQUIRED, 'Path to salary data CSV file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $employeesFile = $input->getArgument('employees_file');
        $structuresFile = $input->getArgument('structures_file');
        $dataFile = $input->getArgument('data_file');

        try {
            $io->section('Importing Employees...');
            $employeeMap = $this->importEmployees($employeesFile, $io);
            
            $io->section('Importing Salary Structures...');
            $this->importSalaryStructures($structuresFile, $io);

            $io->section('Importing Salary Slips...');
            $this->importSalaryData($dataFile, $employeeMap, $io);

            $io->success('All imports completed successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('An error occurred during CSV import: ' . $e->getMessage(), ['exception' => $e]);
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function importEmployees(string $filePath, SymfonyStyle $io): array
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);
        $employeeMap = [];
        $successCount = 0;

        $requiredFields = ['Ref', 'Nom', 'Prenom', 'genre', 'Date embauche', 'date naissance', 'company'];
        $headers = $csv->getHeader();
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier employés - Champ requis manquant : $field");
            }
        }

        foreach ($csv->getRecords() as $record) {
            try {
                $companyName = $record['company'];
                $this->ensureCompanyExists($companyName, $io);

                $employeeData = [
                    'doctype' => 'Employee',
                    'employee_number' => $record['Ref'],
                    'first_name' => $record['Prenom'],
                    'last_name' => $record['Nom'],
                    'employee_name' => $record['Prenom'] . ' ' . $record['Nom'],
                    'gender' => ($record['genre'] === 'Masculin') ? 'Male' : 'Female',
                    'date_of_joining' => $this->convertDate($record['Date embauche']),
                    'date_of_birth' => $this->convertDate($record['date naissance']),
                    'company' => $record['company'],
                ];
                
                $response = $this->erpNextService->addEmployee($employeeData);
                $employeeMap[$record['Ref']] = $response['name'];
                $successCount++;
                $io->writeln(sprintf('  - Employee %s %s imported.', $record['Prenom'], $record['Nom']));
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Failed to import employee "%s": %s', $record['Ref'], $e->getMessage()));
                $io->warning(sprintf('Failed to import employee "%s": %s', $record['Ref'], $e->getMessage()));
            }
        }
        $io->success(sprintf('%d employees imported successfully.', $successCount));
        return $employeeMap;
    }

    private function importSalaryStructures(string $filePath, SymfonyStyle $io): void
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);
        $structures = [];

        $requiredFields = ['salary structure', 'name', 'Abbr', 'type', 'valeur', 'company'];
        $headers = $csv->getHeader();
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier structures salariales - Champ requis manquant : $field");
            }
        }

        foreach ($csv->getRecords() as $record) {
            $companyName = $record['company'];
            $this->ensureCompanyExists($companyName, $io);
            $structures[$record['salary structure']]['components'][] = $record;
            $structures[$record['salary structure']]['company'] = $companyName;
        }

        foreach ($structures as $name => $data) {
            try {
                foreach ($data['components'] as $component) {
                    $isFormulaBased = ($component['valeur'] !== 'base');
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
                }

                $earnings = [];
                $deductions = [];
                foreach ($data['components'] as $component) {
                    $item = ['salary_component' => $component['name'], 'formula' => ($component['valeur'] !== 'base') ? $component['valeur'] : null];
                    if ($component['type'] === 'earning') {
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
                ];
                $this->erpNextService->saveSalaryStructure($structureData);
                $io->writeln(sprintf('  - Salary structure "%s" imported.', $name));
            } catch (\Exception $e) {
                $this->logger->error('Salary structure import failed: ' . $e->getMessage(), ['structure' => $name, 'error' => $e->getMessage()]);
                $io->warning(sprintf('Failed to import salary structure "%s": %s', $name, $e->getMessage()));
            }
        }
        $io->success('Salary structures processed.');
    }

    private function importSalaryData(string $filePath, array $employeeMap, SymfonyStyle $io): void
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);
        $successCount = 0;

        $requiredFields = ['Mois', 'Ref Employe', 'Salaire Base', 'Salaire'];
        $headers = $csv->getHeader();
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier données salariales - Champ requis manquant : $field");
            }
        }

        foreach ($csv->getRecords() as $record) {
            try {
                $employeeRef = $record['Ref Employe'];
                if (!isset($employeeMap[$employeeRef])) {
                    throw new \Exception("Employee Ref $employeeRef not found");
                }
                $employeeId = $employeeMap[$employeeRef];

                $structureName = $record['Salaire'];
                if (!$this->erpNextService->getSalaryStructure($structureName)) {
                    throw new \RuntimeException("Salary structure '$structureName' not found");
                }

                $startDate = \DateTime::createFromFormat('d/m/Y', $record['Mois']);
                $endDate = (clone $startDate)->modify('last day of this month');

                // Check if salary structure assignment exists for employee and date
                $assignment = $this->erpNextService->getEmployeeSalaryStructureAssignment($employeeId, $startDate->format('Y-m-d'));
                if (!$assignment) {
                    // Assign salary structure with from_date one day before startDate
                    $assignmentDate = $startDate->modify('-1 day')->format('Y-m-d');
                    $this->erpNextService->assignSalaryStructure($employeeId, $structureName, $assignmentDate);
                    $io->writeln(sprintf('  - Assigned salary structure "%s" to employee ref "%s" from %s.', $structureName, $employeeRef, $assignmentDate));
                }

                $salaryData = [
                    'employee' => $employeeId,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'salary_structure' => $structureName,
                    'base' => $this->parseSalaryAmount($record['Salaire Base'])
                ];

                $this->erpNextService->addSalarySlip($salaryData);
                $successCount++;
                $io->writeln(sprintf('  - Salary slip for employee ref "%s" (month %s) imported.', $employeeRef, $record['Mois']));
            } catch (\Exception $e) {
                $this->logger->error('Salary slip import failed: ' . $e->getMessage(), ['employee_ref' => $record['Ref Employe'] ?? 'unknown', 'month' => $record['Mois'] ?? 'unknown']);
                $io->warning(sprintf('Failed to import salary slip for employee ref "%s" (month %s): %s', $record['Ref Employe'] ?? 'unknown', $record['Mois'] ?? 'unknown', $e->getMessage()));
            }
        }
        $io->success(sprintf('%d salary slips imported successfully.', $successCount));
    }

    private function convertDate(string $date): string
    {
        $d = \DateTime::createFromFormat('d/m/Y', $date);
        if (!$d || $d->format('d/m/Y') !== $date) {
            throw new \InvalidArgumentException("Format de date invalide : $date (attendu: JJ/MM/AAAA)");
        }
        return $d->format('Y-m-d');
    }

    private function ensureCompanyExists(string $companyName, SymfonyStyle $io): void
    {
        if ($this->erpNextService->getCompany($companyName)) {
            return;
        }
        $abbr = implode('', array_map(fn($w) => $w[0], explode(' ', $companyName)));
        try {
            $createdCompany = $this->erpNextService->createCompany($companyName, $abbr);
            if (!isset($createdCompany['name'])) {
                throw new \RuntimeException("Company creation failed");
            }
            $this->logger->info("Company created", ['company' => $createdCompany]);
            $io->writeln(sprintf('  - Company "%s" created.', $companyName));
            sleep(2);
        } catch (\Exception $e) {
            $this->logger->error("Company creation failed", ['company' => $companyName, 'error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to create company '$companyName': " . $e->getMessage());
        }
    }

    private function parseSalaryAmount(string $amount): float
    {
        $cleaned = str_replace([' ', ','], ['', '.'], $amount);
        if (!is_numeric($cleaned)) {
            throw new \InvalidArgumentException("Montant salarial invalide : $amount");
        }
        return (float)$cleaned;
    }
}