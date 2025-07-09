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
    name: 'app:import-employees',
    description: 'Import employees from CSV file'
)]
class ImportEmployeesCommand extends Command
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
            ->addArgument('file', InputArgument::REQUIRED, 'Path to CSV file')
            ->addArgument('company', InputArgument::OPTIONAL, 'Company name', 'My Company');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');
        $companyName = $input->getArgument('company');

        try {
            // Vérifier/créer la company
            $this->logger->info("Checking if company exists", ['company' => $companyName]);
            $company = $this->erpNextService->getCompany($companyName);
            
            if (!$company) {
                $io->note(sprintf('Company "%s" does not exist, attempting to create it...', $companyName));
                $abbr = implode('', array_map(fn($w) => $w[0], explode(' ', $companyName)));
                
                try {
                    $createdCompany = $this->erpNextService->createCompany(
                        $companyName, 
                        $abbr,
                        'USD', // Devise Ariary malgache
                        'Madagascar' // Pays par défaut
                    );
                    
                    if (!isset($createdCompany['name'])) {
                        $this->logger->error("Company creation failed - invalid response", ['response' => $createdCompany]);
                        throw new \RuntimeException("Company creation failed - invalid response from API");
                    }

                    // Vérifier que la société est bien accessible après création
                    $checkCompany = $this->erpNextService->getCompany($companyName);
                    if (!$checkCompany) {
                        throw new \RuntimeException("Company was created but cannot be found in the system");
                    }
                    
                    $this->logger->info("Company created successfully", [
                        'company' => $createdCompany,
                        'name' => $createdCompany['name'],
                        'abbr' => $abbr
                    ]);
                    
                    $io->success(sprintf('Company "%s" created successfully with abbreviation "%s"', $companyName, $abbr));
                    
                    // Attendre et vérifier plusieurs fois que la société est bien disponible
                    $maxAttempts = 5;
                    $attempt = 0;
                    $companyAvailable = false;
                    
                    while ($attempt < $maxAttempts && !$companyAvailable) {
                        sleep(2);
                        $checkCompany = $this->erpNextService->getCompany($companyName);
                        if ($checkCompany) {
                            $companyAvailable = true;
                            $this->logger->info("Company now available in system", ['company' => $checkCompany]);
                        } else {
                            $this->logger->warning("Company not yet available, retrying...", ['attempt' => $attempt + 1]);
                        }
                        $attempt++;
                    }
                    
                    if (!$companyAvailable) {
                        throw new \RuntimeException("Company was created but never became available in the system");
                    }
                    
                } catch (\Exception $e) {
                    $this->logger->error("Failed to create company", [
                        'company' => $companyName,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw new \RuntimeException(sprintf(
                        'Failed to create company "%s": %s', 
                        $companyName, 
                        $e->getMessage()
                    ));
                }
            } else {
                $this->logger->info("Company already exists", ['company' => $company]);
                $io->note(sprintf('Using existing company "%s"', $companyName));
            }

            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);
            $successCount = 0;

            foreach ($csv->getRecords() as $record) {
                try {
                    $employeeData = [
                        'doctype' => 'Employee',
                        'employee_number' => $record['Ref'],
                        'first_name' => $record['Prenom'],
                        'last_name' => $record['Nom'],
                        'employee_name' => $record['Prenom'] . ' ' . $record['Nom'],
                        'gender' => ($record['genre'] === 'Masculin') ? 'Male' : 'Female',
                        'date_of_joining' => $this->convertDate($record['Date embauche']),
                        'date_of_birth' => $this->convertDate($record['date naissance']),
                        'company' => $companyName,
                    ];

                    $response = $this->erpNextService->addEmployee($employeeData);
                    $successCount++;
                    $io->note(sprintf('Employee %s %s imported successfully', $record['Prenom'], $record['Nom']));
                } catch (\Exception $e) {
                    $this->logger->error(sprintf('Failed to import employee "%s": %s', $record['Ref'], $e->getMessage()));
                    $io->error(sprintf('Failed to import employee "%s": %s', $record['Ref'], $e->getMessage()));
                }
            }

            $io->success(sprintf('%d employees imported successfully.', $successCount));
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->logger->error('Import failed: ' . $e->getMessage());
            $io->error('Import failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function convertDate(string $date): string
    {
        $d = \DateTime::createFromFormat('d/m/Y', $date);
        if (!$d || $d->format('d/m/Y') !== $date) {
            throw new \InvalidArgumentException("Invalid date format: $date (expected: DD/MM/YYYY)");
        }
        return $d->format('Y-m-d');
    }
}
