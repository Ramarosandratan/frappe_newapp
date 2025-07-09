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
    name: 'app:import-salary-structures',
    description: 'Import salary structures from CSV file'
)]
class ImportSalaryStructuresCommand extends Command
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
            ->addArgument('file', InputArgument::REQUIRED, 'Path to CSV file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        try {
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);
            $structures = [];

            // Validation des champs requis
            $requiredFields = ['salary structure', 'name', 'Abbr', 'type', 'valeur', 'company'];
            $headers = $csv->getHeader();
            foreach ($requiredFields as $field) {
                if (!in_array($field, $headers)) {
                    throw new \RuntimeException("Fichier structures salariales - Champ requis manquant : $field");
                }
            }

            foreach ($csv->getRecords() as $record) {
                // Validation individuelle
                foreach ($requiredFields as $field) {
                    if (empty($record[$field])) {
                        throw new \RuntimeException("Missing value for required field: $field in structure {$record['salary structure']}");
                    }
                }
                
                $companyName = $record['company'];
                $this->ensureCompanyExists($companyName, $io);

                $structures[$record['salary structure']]['components'][] = $record;
                $structures[$record['salary structure']]['company'] = $companyName;
            }

            foreach ($structures as $name => $data) {
                try {
                    // Créer/mettre à jour les composants
                    foreach ($data['components'] as $component) {
                        try {
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
                        } catch (\Exception $e) {
                            $this->logger->warning('Component creation issue: ' . $e->getMessage(), [
                                'component' => $component['name'],
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    // Préparer les gains et déductions
                    $earnings = [];
                    $deductions = [];
                    foreach ($data['components'] as $component) {
                        $item = [
                            'salary_component' => $component['name'],
                            'formula' => ($component['valeur'] !== 'base') ? $component['valeur'] : null,
                        ];
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
                    $io->success(sprintf('Salary structure "%s" imported successfully.', $name));
                } catch (\Exception $e) {
                    $this->logger->error('Salary structure import failed: ' . $e->getMessage(), [
                        'structure' => $name,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $io->warning(sprintf('Failed to import salary structure "%s": %s', $name, $e->getMessage()));
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Import failed: ' . $e->getMessage());
            $io->error('Import failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function ensureCompanyExists(string $companyName, SymfonyStyle $io): void
    {
        if ($this->erpNextService->getCompany($companyName)) {
            $io->note("Company '$companyName' already exists.");
            return;
        }

        $io->note("Company '$companyName' does not exist, creating it...");
        $abbr = implode('', array_map(fn($w) => $w[0], explode(' ', $companyName)));
        try {
            $createdCompany = $this->erpNextService->createCompany($companyName, $abbr);
            if (!isset($createdCompany['name'])) {
                throw new \RuntimeException("Company creation failed");
            }
            $this->logger->info("Company created", ['company' => $createdCompany]);
            $io->success(sprintf('Company "%s" created successfully', $companyName));
            sleep(2); // Wait for ERPNext to process
        } catch (\Exception $e) {
            $this->logger->error("Company creation failed", [
                'company' => $companyName,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Failed to create company '$companyName': " . $e->getMessage());
        }
    }
}