<?php

namespace App\Command;

// Importe les classes nécessaires pour le fonctionnement de la commande.
use App\Service\ErpNextService; // Service pour interagir avec l'API ERPNext.
use League\Csv\Reader; // Bibliothèque pour lire les fichiers CSV.
use Psr\Log\LoggerInterface; // Interface pour la journalisation des événements.
use Symfony\Component\Console\Attribute\AsCommand; // Attribut pour déclarer une commande Symfony.
use Symfony\Component\Console\Command\Command; // Classe de base pour les commandes Symfony.
use Symfony\Component\Console\Input\InputArgument; // Pour définir les arguments de la commande.
use Symfony\Component\Console\Input\InputInterface; // Interface pour l'entrée de la commande.
use Symfony\Component\Console\Output\OutputInterface; // Interface pour la sortie de la commande.
use Symfony\Component\Console\Style\SymfonyStyle; // Pour un style d'interface utilisateur amélioré dans la console.

/**
 * Commande Symfony pour importer les fiches de paie (salary slips) à partir de fichiers CSV.
 *
 * Cette commande prend en entrée trois fichiers CSV :
 * - Un fichier d'employés.
 * - Un fichier de structures salariales.
 * - Un fichier de données salariales.
 * Elle interagit avec un service ERPNext pour créer ou mettre à jour les entités correspondantes.
 */
#[AsCommand(
    name: 'app:import-salary-slips', // Nom de la commande, utilisé pour l'exécuter en ligne de commande.
    description: 'Import salary slips from CSV files' // Description courte de la commande.
)]
class ImportSalarySlipsCommand extends Command
{
    /**
     * Constructeur de la commande.
     *
     * @param ErpNextService $erpNextService Service pour les opérations ERPNext.
     * @param LoggerInterface $logger Service de journalisation.
     */
    public function __construct(
        private readonly ErpNextService $erpNextService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct(); // Appelle le constructeur de la classe parente.
    }

    /**
     * Configure les arguments et les options de la commande.
     */
    protected function configure(): void
    {
        $this
            // Ajoute un argument requis pour le chemin du fichier CSV des employés.
            ->addArgument('employees_file', InputArgument::REQUIRED, 'Path to employees CSV file')
            // Ajoute un argument requis pour le chemin du fichier CSV des structures salariales.
            ->addArgument('structures_file', InputArgument::REQUIRED, 'Path to salary structures CSV file')
            // Ajoute un argument requis pour le chemin du fichier CSV des données salariales.
            ->addArgument('data_file', InputArgument::REQUIRED, 'Path to salary data CSV file');
    }

    /**
     * Exécute la logique principale de la commande.
     *
     * @param InputInterface $input L'interface d'entrée.
     * @param OutputInterface $output L'interface de sortie.
     * @return int Le code de sortie de la commande (SUCCESS ou FAILURE).
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Crée une instance de SymfonyStyle pour une sortie console stylisée.
        $io = new SymfonyStyle($input, $output);
        // Récupère les chemins des fichiers CSV à partir des arguments.
        $employeesFile = $input->getArgument('employees_file');
        $structuresFile = $input->getArgument('structures_file');
        $dataFile = $input->getArgument('data_file');

        try {
            // Importe les employés et stocke le mappage des références.
            $io->section('Importing Employees...');
            $employeeMap = $this->importEmployees($employeesFile, $io);
            
            // Importe les structures salariales.
            $io->section('Importing Salary Structures...');
            $this->importSalaryStructures($structuresFile, $io);

            // Importe les données salariales (fiches de paie).
            $io->section('Importing Salary Slips...');
            $this->importSalaryData($dataFile, $employeeMap, $io);

            // Affiche un message de succès si toutes les importations sont terminées.
            $io->success('All imports completed successfully!');
            return Command::SUCCESS; // Retourne un code de succès.
        } catch (\Exception $e) {
            // En cas d'erreur, journalise l'exception et affiche un message d'erreur.
            $this->logger->error('An error occurred during CSV import: ' . $e->getMessage(), ['exception' => $e]);
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE; // Retourne un code d'échec.
        }
    }

    /**
     * Importe les données des employés à partir d'un fichier CSV.
     *
     * @param string $filePath Le chemin du fichier CSV des employés.
     * @param SymfonyStyle $io L'instance SymfonyStyle pour l'interaction console.
     * @return array Un tableau associatif mappant les références d'employés aux IDs ERPNext.
     * @throws \RuntimeException Si un champ requis est manquant dans le fichier CSV.
     */
    private function importEmployees(string $filePath, SymfonyStyle $io): array
    {
        // Crée un lecteur CSV à partir du chemin du fichier.
        $csv = Reader::createFromPath($filePath, 'r');
        // Définit la première ligne comme en-tête.
        $csv->setHeaderOffset(0);
        $employeeMap = []; // Initialise le tableau de mappage des employés.
        $successCount = 0; // Compteur d'employés importés avec succès.

        // Champs requis dans le fichier CSV des employés.
        $requiredFields = ['Ref', 'Nom', 'Prenom', 'genre', 'Date embauche', 'date naissance', 'company'];
        $headers = $csv->getHeader(); // Récupère les en-têtes du CSV.
        // Vérifie si tous les champs requis sont présents.
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier employés - Champ requis manquant : $field");
            }
        }

        // Parcourt chaque enregistrement du fichier CSV.
        foreach ($csv->getRecords() as $record) {
            try {
                $companyName = $record['company'];
                // S'assure que l'entreprise existe dans ERPNext, la crée si nécessaire.
                $this->ensureCompanyExists($companyName, $io);

                // Prépare les données de l'employé pour l'API ERPNext.
                $employeeData = [
                    'doctype' => 'Employee',
                    'employee_number' => $record['Ref'],
                    'first_name' => $record['Prenom'],
                    'last_name' => $record['Nom'],
                    'employee_name' => $record['Prenom'] . ' ' . $record['Nom'],
                    'gender' => ($record['genre'] === 'Masculin') ? 'Male' : 'Female', // Convertit le genre.
                    'date_of_joining' => $this->convertDate($record['Date embauche']), // Convertit la date d'embauche.
                    'date_of_birth' => $this->convertDate($record['date naissance']), // Convertit la date de naissance.
                    'company' => $record['company'],
                ];
                
                // Ajoute l'employé via le service ERPNext.
                $response = $this->erpNextService->addEmployee($employeeData);
                // Mappe la référence de l'employé à son nom (ID) dans ERPNext.
                $employeeMap[$record['Ref']] = $response['name'];
                $successCount++; // Incrémente le compteur de succès.
                // Affiche un message de succès pour l'employé importé.
                $io->writeln(sprintf('  - Employee %s %s imported.', $record['Prenom'], $record['Nom']));
            } catch (\Exception $e) {
                // En cas d'échec d'importation d'un employé, journalise l'erreur et affiche un avertissement.
                $this->logger->error(sprintf('Failed to import employee "%s": %s', $record['Ref'], $e->getMessage()));
                $io->warning(sprintf('Failed to import employee "%s": %s', $record['Ref'], $e->getMessage()));
            }
        }
        // Affiche un résumé du nombre d'employés importés.
        $io->success(sprintf('%d employees imported successfully.', $successCount));
        return $employeeMap; // Retourne le mappage des employés.
    }

    /**
     * Importe les structures salariales à partir d'un fichier CSV.
     *
     * @param string $filePath Le chemin du fichier CSV des structures salariales.
     * @param SymfonyStyle $io L'instance SymfonyStyle pour l'interaction console.
     * @throws \RuntimeException Si un champ requis est manquant dans le fichier CSV.
     */
    private function importSalaryStructures(string $filePath, SymfonyStyle $io): void
    {
        // Crée un lecteur CSV à partir du chemin du fichier.
        $csv = Reader::createFromPath($filePath, 'r');
        // Définit la première ligne comme en-tête.
        $csv->setHeaderOffset(0);
        $structures = []; // Tableau pour regrouper les composants par structure salariale.

        // Champs requis dans le fichier CSV des structures salariales.
        $requiredFields = ['salary structure', 'name', 'Abbr', 'type', 'valeur', 'company'];
        $headers = $csv->getHeader(); // Récupère les en-têtes du CSV.
        // Vérifie si tous les champs requis sont présents.
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier structures salariales - Champ requis manquant : $field");
            }
        }

        // Parcourt chaque enregistrement pour regrouper les composants par structure.
        foreach ($csv->getRecords() as $record) {
            $companyName = $record['company'];
            // S'assure que l'entreprise existe dans ERPNext.
            $this->ensureCompanyExists($companyName, $io);
            $structures[$record['salary structure']]['components'][] = $record;
            $structures[$record['salary structure']]['company'] = $companyName;
        }

        // Parcourt chaque structure salariale regroupée.
        foreach ($structures as $name => $data) {
            try {
                // Traite et sauvegarde chaque composant salarial.
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

                $earnings = []; // Composants de gains.
                $deductions = []; // Composants de déductions.
                // Sépare les composants en gains et déductions.
                foreach ($data['components'] as $component) {
                    $item = ['salary_component' => $component['name'], 'formula' => ($component['valeur'] !== 'base') ? $component['valeur'] : null];
                    if ($component['type'] === 'earning') {
                        $earnings[] = $item;
                    } else {
                        $deductions[] = $item;
                    }
                }

                // Prépare les données de la structure salariale.
                $structureData = [
                    'doctype' => 'Salary Structure',
                    'name' => $name,
                    'company' => $data['company'],
                    'earnings' => $earnings,
                    'deductions' => $deductions,
                ];
                // Sauvegarde la structure salariale via le service ERPNext.
                $this->erpNextService->saveSalaryStructure($structureData);
                // Affiche un message de succès pour la structure importée.
                $io->writeln(sprintf('  - Salary structure "%s" imported.', $name));
            } catch (\Exception $e) {
                // En cas d'échec d'importation d'une structure, journalise l'erreur et affiche un avertissement.
                $this->logger->error('Salary structure import failed: ' . $e->getMessage(), ['structure' => $name, 'error' => $e->getMessage()]);
                $io->warning(sprintf('Failed to import salary structure "%s": %s', $name, $e->getMessage()));
            }
        }
        // Affiche un message de succès général pour les structures salariales.
        $io->success('Salary structures processed.');
    }

    /**
     * Importe les données salariales (fiches de paie) à partir d'un fichier CSV.
     *
     * @param string $filePath Le chemin du fichier CSV des données salariales.
     * @param array $employeeMap Le mappage des références d'employés aux IDs ERPNext.
     * @param SymfonyStyle $io L'instance SymfonyStyle pour l'interaction console.
     * @throws \RuntimeException Si un champ requis est manquant ou si la structure salariale n'est pas trouvée.
     * @throws \Exception Si la référence de l'employé n'est pas trouvée.
     */
    private function importSalaryData(string $filePath, array $employeeMap, SymfonyStyle $io): void
    {
        // Crée un lecteur CSV à partir du chemin du fichier.
        $csv = Reader::createFromPath($filePath, 'r');
        // Définit la première ligne comme en-tête.
        $csv->setHeaderOffset(0);
        $successCount = 0; // Compteur de fiches de paie importées avec succès.

        // Champs requis dans le fichier CSV des données salariales.
        $requiredFields = ['Mois', 'Ref Employe', 'Salaire Base', 'Salaire'];
        $headers = $csv->getHeader(); // Récupère les en-têtes du CSV.
        // Vérifie si tous les champs requis sont présents.
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier données salariales - Champ requis manquant : $field");
            }
        }

        // Parcourt chaque enregistrement du fichier CSV.
        foreach ($csv->getRecords() as $record) {
            try {
                $employeeRef = $record['Ref Employe'];
                // Vérifie si la référence de l'employé existe dans le mappage.
                if (!isset($employeeMap[$employeeRef])) {
                    throw new \Exception("Employee Ref $employeeRef not found");
                }
                $employeeId = $employeeMap[$employeeRef]; // Récupère l'ID ERPNext de l'employé.

                $structureName = $record['Salaire'];
                // Vérifie si la structure salariale existe dans ERPNext.
                if (!$this->erpNextService->getSalaryStructure($structureName)) {
                    throw new \RuntimeException("Salary structure '$structureName' not found");
                }

                // Convertit la date du mois en objets DateTime pour le début et la fin du mois.
                $startDate = \DateTime::createFromFormat('d/m/Y', $record['Mois']);
                $endDate = (clone $startDate)->modify('last day of this month');

                // Vérifie si une affectation de structure salariale existe déjà pour l'employé et la date.
                $assignment = $this->erpNextService->getEmployeeSalaryStructureAssignment($employeeId, $startDate->format('Y-m-d'));
                if (!$assignment) {
                    // Si aucune affectation n'existe, en crée une.
                    // La date d'affectation est un jour avant la date de début de la fiche de paie.
                    $assignmentDate = (clone $startDate)->modify('-1 day')->format('Y-m-d');
                    $this->erpNextService->assignSalaryStructure($employeeId, $structureName, $assignmentDate);
                    $io->writeln(sprintf('  - Assigned salary structure "%s" to employee ref "%s" from %s.', $structureName, $employeeRef, $assignmentDate));
                }

                // Prépare les données de la fiche de paie.
                $salaryData = [
                    'employee' => $employeeId,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'salary_structure' => $structureName,
                    'base' => $this->parseSalaryAmount($record['Salaire Base']) // Parse le montant du salaire de base.
                ];

                // Ajoute la fiche de paie via le service ERPNext.
                $this->erpNextService->addSalarySlip($salaryData);
                $successCount++; // Incrémente le compteur de succès.
                // Affiche un message de succès pour la fiche de paie importée.
                $io->writeln(sprintf('  - Salary slip for employee ref "%s" (month %s) imported.', $employeeRef, $record['Mois']));
            } catch (\Exception $e) {
                // En cas d'échec d'importation d'une fiche de paie, journalise l'erreur et affiche un avertissement.
                $this->logger->error('Salary slip import failed: ' . $e->getMessage(), ['employee_ref' => $record['Ref Employe'] ?? 'unknown', 'month' => $record['Mois'] ?? 'unknown']);
                $io->warning(sprintf('Failed to import salary slip for employee ref "%s" (month %s): %s', $record['Ref Employe'] ?? 'unknown', $record['Mois'] ?? 'unknown', $e->getMessage()));
            }
        }
        // Affiche un résumé du nombre de fiches de paie importées.
        $io->success(sprintf('%d salary slips imported successfully.', $successCount));
    }

    /**
     * Convertit une chaîne de date au format JJ/MM/AAAA en AAAA-MM-JJ.
     *
     * @param string $date La date à convertir.
     * @return string La date convertie au format AAAA-MM-JJ.
     * @throws \InvalidArgumentException Si le format de la date est invalide.
     */
    private function convertDate(string $date): string
    {
        // Tente de créer un objet DateTime à partir du format JJ/MM/AAAA.
        $d = \DateTime::createFromFormat('d/m/Y', $date);
        // Vérifie si la conversion a réussi et si la date originale correspond à la date formatée.
        if (!$d || $d->format('d/m/Y') !== $date) {
            throw new \InvalidArgumentException("Format de date invalide : $date (attendu: JJ/MM/AAAA)");
        }
        return $d->format('Y-m-d'); // Retourne la date au format AAAA-MM-JJ.
    }

    /**
     * S'assure qu'une entreprise existe dans ERPNext, la crée si elle n'existe pas.
     *
     * @param string $companyName Le nom de l'entreprise.
     * @param SymfonyStyle $io L'instance SymfonyStyle pour l'interaction console.
     * @throws \RuntimeException Si la création de l'entreprise échoue.
     */
    private function ensureCompanyExists(string $companyName, SymfonyStyle $io): void
    {
        // Vérifie si l'entreprise existe déjà.
        if ($this->erpNextService->getCompany($companyName)) {
            return; // Si elle existe, ne fait rien.
        }
        // Génère une abréviation pour l'entreprise.
        $abbr = implode('', array_map(fn($w) => $w[0], explode(' ', $companyName)));
        try {
            // Tente de créer l'entreprise via le service ERPNext.
            $createdCompany = $this->erpNextService->createCompany($companyName, $abbr);
            // Vérifie si la création a réussi.
            if (!isset($createdCompany['name'])) {
                throw new \RuntimeException("Company creation failed");
            }
            // Journalise la création de l'entreprise.
            $this->logger->info("Company created", ['company' => $createdCompany]);
            // Affiche un message de succès.
            $io->writeln(sprintf('  - Company "%s" created.', $companyName));
            sleep(2); // Pause pour laisser le temps à ERPNext de traiter.
        } catch (\Exception $e) {
            // En cas d'échec de création, journalise l'erreur et lance une exception.
            $this->logger->error("Company creation failed", ['company' => $companyName, 'error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to create company '$companyName': " . $e->getMessage());
        }
    }

    /**
     * Parse un montant salarial en chaîne de caractères et le convertit en float.
     * Gère les espaces et les virgules comme séparateurs décimaux.
     *
     * @param string $amount Le montant salarial sous forme de chaîne.
     * @return float Le montant salarial converti en float.
     * @throws \InvalidArgumentException Si le montant n'est pas numérique après nettoyage.
     */
    private function parseSalaryAmount(string $amount): float
    {
        // Remplace les espaces et les virgules par des points pour la conversion numérique.
        $cleaned = str_replace([' ', ','], ['', '.'], $amount);
        // Vérifie si la chaîne nettoyée est un nombre valide.
        if (!is_numeric($cleaned)) {
            throw new \InvalidArgumentException("Montant salarial invalide : $amount");
        }
        return (float)$cleaned; // Retourne le montant converti en float.
    }
}