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

/**
 * Contrôleur responsable de l'importation des données (employés, structures salariales, fiches de paie)
 * à partir de fichiers CSV vers le système ERPNext.
 */
class ImportController extends AbstractController
{
    /**
     * Constructeur du contrôleur.
     *
     * @param ErpNextService $erpNextService Service pour interagir avec l'API ERPNext.
     * @param LoggerInterface $logger Service de journalisation pour enregistrer les informations et les erreurs.
     */
    public function __construct(
        private readonly ErpNextService $erpNextService,
        private readonly LoggerInterface $logger
    ) {}
    
    /**
     * Temps d'exécution maximal autorisé pour le processus d'importation en secondes.
     */
    private const IMPORT_TIMEOUT = 300; // 5 minutes
    
    /**
     * Nombre maximal d'enregistrements à traiter par lot pour éviter la surcharge mémoire.
     */
    private const BATCH_LIMIT = 100;

    /**
     * Affiche la page d'aide pour les formats de fichiers CSV.
     *
     * @return Response La réponse HTTP.
     */
    #[Route('/import/help', name: 'app_import_help', methods: ['GET'])]
    public function help(): Response
    {
        return $this->render('import/help.html.twig');
    }

    /**
     * Gère la page d'importation, affichant le formulaire et traitant les soumissions de fichiers CSV.
     *
     * @param Request $request L'objet requête HTTP.
     * @return Response La réponse HTTP, redirigeant ou affichant le formulaire.
     */
    #[Route('/import', name: 'app_import', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        // Configure les timeouts pour les imports longs
        $this->configureTimeouts();
        
        // Crée le formulaire pour l'importation de plusieurs fichiers CSV.
        $form = $this->createForm(MultiCsvImportType::class);
        // Gère la soumission du formulaire.
        $form->handleRequest($request);

        $this->logger->info('Méthode index de ImportController appelée.');
        $this->logger->info('Statut de soumission du formulaire', ['isSubmitted' => $form->isSubmitted()]);

        // Vérifie si le formulaire a été soumis et est valide.
        if ($form->isSubmitted() && $form->isValid()) {
            $this->logger->info('Statut de validité du formulaire', ['isValid' => $form->isValid()]);
            
            // Récupère les fichiers téléchargés depuis le formulaire.
            /** @var UploadedFile $employeeFile Fichier CSV des employés. */
            $employeeFile = $form->get('employee_file')->getData();
            /** @var UploadedFile $structureFile Fichier CSV des structures salariales. */
            $structureFile = $form->get('structure_file')->getData();
            /** @var UploadedFile $dataFile Fichier CSV des données salariales. */
            $dataFile = $form->get('data_file')->getData();

            try {
                // Analyse les fichiers CSV pour obtenir les informations de base
                $analysisResult = $this->analyzeFiles($employeeFile, $structureFile, $dataFile);
                
                // Sauvegarde les fichiers dans un répertoire temporaire persistant
                $tempDir = sys_get_temp_dir() . '/csv_import_' . uniqid();
                if (!mkdir($tempDir, 0755, true)) {
                    throw new \RuntimeException('Impossible de créer le répertoire temporaire');
                }
                
                $employeeFilePath = $tempDir . '/' . $employeeFile->getClientOriginalName();
                $structureFilePath = $tempDir . '/' . $structureFile->getClientOriginalName();
                $dataFilePath = $tempDir . '/' . $dataFile->getClientOriginalName();
                
                // Copie les fichiers vers le répertoire temporaire
                if (!copy($employeeFile->getPathname(), $employeeFilePath) ||
                    !copy($structureFile->getPathname(), $structureFilePath) ||
                    !copy($dataFile->getPathname(), $dataFilePath)) {
                    throw new \RuntimeException('Impossible de sauvegarder les fichiers temporaires');
                }
                
                // Stocke les informations d'analyse en session pour la confirmation
                $request->getSession()->set('csv_analysis', $analysisResult);
                $request->getSession()->set('csv_files', [
                    'employee_file' => $employeeFilePath,
                    'structure_file' => $structureFilePath,
                    'data_file' => $dataFilePath,
                    'employee_filename' => $employeeFile->getClientOriginalName(),
                    'structure_filename' => $structureFile->getClientOriginalName(),
                    'data_filename' => $dataFile->getClientOriginalName(),
                    'temp_dir' => $tempDir,
                ]);

                // Redirige vers la page de confirmation
                return $this->redirectToRoute('app_import_confirm');
                
            } catch (\Throwable $e) {
                $this->logger->error('Erreur lors de l\'analyse des fichiers CSV : ' . $e->getMessage(), ['exception' => $e]);
                $this->addFlash('danger', 'Erreur lors de l\'analyse des fichiers : ' . $e->getMessage());
            }
        }

        // Affiche le formulaire d'importation si non soumis ou invalide.
        return $this->render('import/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Affiche la page de confirmation avec l'analyse des fichiers CSV.
     *
     * @param Request $request L'objet requête HTTP.
     * @return Response La réponse HTTP.
     */
    #[Route('/import/confirm', name: 'app_import_confirm', methods: ['GET', 'POST'])]
    public function confirm(Request $request): Response
    {
        // Récupère les données d'analyse depuis la session
        $analysisResult = $request->getSession()->get('csv_analysis');
        $csvFiles = $request->getSession()->get('csv_files');

        if (!$analysisResult || !$csvFiles) {
            $this->addFlash('warning', 'Aucune analyse de fichier trouvée. Veuillez recommencer.');
            return $this->redirectToRoute('app_import');
        }

        // Si l'utilisateur confirme l'importation
        if ($request->isMethod('POST') && $request->request->get('action') === 'confirm') {
            return $this->processImport($request, $csvFiles);
        }

        // Si l'utilisateur annule
        if ($request->isMethod('POST') && $request->request->get('action') === 'cancel') {
            // Nettoie les fichiers temporaires
            if (isset($csvFiles['temp_dir']) && is_dir($csvFiles['temp_dir'])) {
                $this->cleanupTempDirectory($csvFiles['temp_dir']);
            }
            
            $request->getSession()->remove('csv_analysis');
            $request->getSession()->remove('csv_files');
            $this->addFlash('info', 'Importation annulée.');
            return $this->redirectToRoute('app_import');
        }

        // Affiche la page de confirmation
        return $this->render('import/confirm.html.twig', [
            'analysis' => $analysisResult,
            'files' => $csvFiles,
        ]);
    }

    /**
     * Traite l'importation des fichiers CSV après confirmation.
     *
     * @param Request $request L'objet requête HTTP.
     * @param array $csvFiles Les informations des fichiers CSV.
     * @return Response La réponse HTTP.
     */
    private function processImport(Request $request, array $csvFiles): Response
    {
        // Définit une limite de temps d'exécution pour le script afin d'éviter les blocages.
        set_time_limit(self::IMPORT_TIMEOUT);

        $this->logger->info('Démarrage du processus d\'importation CSV confirmé', [
            'employee_file' => $csvFiles['employee_filename'],
            'structure_file' => $csvFiles['structure_filename'],
            'data_file' => $csvFiles['data_filename'],
        ]);

        try {
            // Vérifie que les fichiers existent
            if (!file_exists($csvFiles['employee_file']) || 
                !file_exists($csvFiles['structure_file']) || 
                !file_exists($csvFiles['data_file'])) {
                throw new \RuntimeException('Un ou plusieurs fichiers CSV sont manquants');
            }

            // Recrée les objets UploadedFile à partir des chemins stockés
            $employeeFile = new UploadedFile($csvFiles['employee_file'], $csvFiles['employee_filename'], null, null, true);
            $structureFile = new UploadedFile($csvFiles['structure_file'], $csvFiles['structure_filename'], null, null, true);
            $dataFile = new UploadedFile($csvFiles['data_file'], $csvFiles['data_filename'], null, null, true);

            // Importe les employés et stocke leur correspondance de référence.
            $startTime = microtime(true);
            $employeeMap = $this->importEmployees($employeeFile);
            $this->logger->info('Importation des employés terminée', ['duration' => microtime(true) - $startTime]);
            
            // Importe les structures salariales en utilisant la correspondance des employés.
            $startTime = microtime(true);
            $this->importSalaryStructures($structureFile, $employeeMap);
            $this->logger->info('Importation des structures salariales terminée', ['duration' => microtime(true) - $startTime]);
            
            // Importe les données salariales en utilisant la correspondance des employés.
            $startTime = microtime(true);
            $this->importSalaryData($dataFile, $employeeMap);
            $this->logger->info('Importation des données salariales terminée', ['duration' => microtime(true) - $startTime]);

            $this->addFlash('success', 'Importation terminée avec succès !');
            
        } catch (\Throwable $e) {
            // Capture et journalise toute exception survenue pendant l'importation.
            $this->logger->error('Une erreur est survenue pendant l\'importation CSV : ' . $e->getMessage(), ['exception' => $e]);
            // Ajoute un message flash d'erreur pour l'utilisateur.
            $this->addFlash('danger', 'Une erreur est survenue : ' . $e->getMessage());
        } finally {
            // Nettoie les fichiers temporaires
            if (isset($csvFiles['temp_dir']) && is_dir($csvFiles['temp_dir'])) {
                $this->cleanupTempDirectory($csvFiles['temp_dir']);
            }
            
            // Nettoie la session
            $request->getSession()->remove('csv_analysis');
            $request->getSession()->remove('csv_files');
        }

        // Redirige vers la page d'importation après le traitement.
        return $this->redirectToRoute('app_import');
    }

    /**
     * Importe les données des employés à partir d'un fichier CSV.
     *
     * @param UploadedFile $file Le fichier CSV contenant les données des employés.
     * @return array Un tableau associatif mappant la référence de l'employé à son nom ERPNext.
     * @throws \RuntimeException Si un champ requis est manquant ou une valeur est invalide.
     */
    private function importEmployees(UploadedFile $file): array
    {
        // Crée un lecteur CSV à partir du fichier téléchargé avec gestion de l'encodage.
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        
        // Détecte et configure l'encodage du fichier CSV
        $this->configureCsvEncoding($csv);
        
        // Définit la première ligne comme en-tête.
        $csv->setHeaderOffset(0);
        $employeeMap = []; // Mappe la référence de l'employé au nom ERPNext.
        $successCount = 0; // Compteur d'importations réussies.
        $errorCount = 0;   // Compteur d'importations échouées.
        $companies = [];   // Liste des entreprises uniques rencontrées.
        $processedCount = 0; // Compteur d'enregistrements traités.

        // Champs requis dans le fichier CSV des employés.
        $requiredFields = ['Ref', 'Nom', 'Prenom', 'genre', 'Date embauche', 'date naissance', 'company'];
        $headers = $csv->getHeader();
        // Vérifie la présence de tous les champs requis.
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier employés - Champ requis manquant : $field");
            }
        }

        $records = iterator_to_array($csv->getRecords());
        $this->logger->info('Démarrage de l\'importation des employés depuis le CSV', ['file' => $file->getClientOriginalName(), 'total_records' => count($records)]);

        // Parcourt chaque enregistrement du CSV.
        foreach ($csv->getRecords() as $index => $record) {
            $processedCount++;
            // Vérifie si la limite de traitement par lot est atteinte.
            if ($processedCount > self::BATCH_LIMIT) {
                $this->logger->warning('Limite de lot atteinte pour l\'importation des employés', ['limit' => self::BATCH_LIMIT]);
                $this->addFlash('warning', sprintf('Limite de traitement atteinte. Seuls les %d premiers employés ont été importés.', self::BATCH_LIMIT));
                break; // Arrête le traitement si la limite est atteinte.
            }

            try {
                $this->logger->debug('Traitement de l\'enregistrement employé', ['line' => $index + 2, 'ref' => $record['Ref'] ?? 'inconnu']);
                // Vérifie que tous les champs requis ont une valeur.
                foreach ($requiredFields as $field) {
                    if (!isset($record[$field]) || trim($record[$field]) === '') {
                        throw new \RuntimeException("Valeur manquante pour le champ '$field' à la ligne " . ($index + 2));
                    }
                }
                $companyName = trim($record['company']);
                // S'assure que le nom de l'entreprise n'est pas vide.
                if ($companyName === '') {
                    throw new \RuntimeException("Nom de l'entreprise vide à la ligne " . ($index + 2));
                }

                // Normalise le nom de l'entreprise pour une utilisation interne (minuscules).
                $companyNameNormalized = mb_strtolower($companyName);

                // S'assure que l'entreprise existe dans ERPNext, la crée si nécessaire.
                $this->ensureCompanyExists($companyName);

                // Enregistre les entreprises uniques pour la configuration des listes de jours fériés.
                if (!in_array($companyName, $companies, true)) {
                    $companies[] = $companyName;
                }

                // Tente de vérifier si l'employé existe déjà dans ERPNext.
                try {
                    $existingEmployee = $this->erpNextService->getEmployeeByNumber($record['Ref']);
                    if (!empty($existingEmployee)) {
                        // Si l'employé existe, l'ajoute à la carte et incrémente le compteur de succès.
                        $employeeMap[$record['Ref']] = $existingEmployee[0]['name'];
                        $successCount++;
                        $this->logger->debug('L\'employé existe déjà', ['ref' => $record['Ref'], 'name' => $existingEmployee[0]['name']]);

                        // Tente d'associer la liste de jours fériés à l'employé existant.
                        try {
                            $holidayListName = $companyName . " Holidays " . date('Y');
                            $this->erpNextService->setEmployeeHolidayList($existingEmployee[0]['name'], $holidayListName);
                        } catch (\Throwable $e) {
                            $this->logger->warning("Échec de l'association de la liste de jours fériés à l'employé existant", [
                                'employee' => $existingEmployee[0]['name'],
                                'error' => $e->getMessage()
                            ]);
                        }

                        continue; // Passe à l'enregistrement suivant.
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning("Erreur lors de la vérification de l'employé existant", [
                        'ref' => $record['Ref'],
                        'error' => $e->getMessage()
                    ]);
                    // Continue pour tenter de créer l'employé même en cas d'erreur de vérification.
                }

                // Convertit les dates d'embauche et de naissance au format Y-m-d.
                try {
                    $dateOfJoining = $this->convertDate($record['Date embauche']);
                    $dateOfBirth = $this->convertDate($record['date naissance']);
                } catch (\Throwable $e) {
                    throw new \RuntimeException("Erreur de format de date: " . $e->getMessage());
                }

                // Prépare les données de l'employé pour l'API ERPNext.
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

                // Valide les données avant l'envoi
                $validationErrors = $this->validateEmployeeData($employeeData);
                if (!empty($validationErrors)) {
                    throw new \RuntimeException('Erreurs de validation: ' . implode(', ', $validationErrors));
                }

                // Appelle l'API ERPNext pour ajouter l'employé.
                $startTime = microtime(true);
                $response = $this->erpNextService->addEmployee($employeeData);
                $this->logger->debug('Appel API pour ajouter l\'employé terminé', ['ref' => $record['Ref'], 'duration' => microtime(true) - $startTime]);

                // Vérifie la réponse de l'API.
                if (isset($response['name'])) {
                    $employeeMap[$record['Ref']] = $response['name'];
                    $successCount++;

                    // Tente de créer et d'associer une liste de jours fériés à l'employé.
                    try {
                        $holidayListName = $companyName . " Holidays " . date('Y');
                        $holidayList = $this->erpNextService->getHolidayList($holidayListName);

                        // Crée la liste de jours fériés si elle n'existe pas.
                        if (empty($holidayList)) {
                            $this->erpNextService->createHolidayList($holidayListName, $companyName);
                        }

                        // Associe la liste de jours fériés à l'employé.
                        $this->erpNextService->setEmployeeHolidayList($response['name'], $holidayListName);
                    } catch (\Throwable $e) {
                        $this->logger->warning("Échec de l'association de la liste de jours fériés à l'employé", [
                            'employee' => $response['name'],
                            'error' => $e->getMessage()
                        ]);
                        // Ne fait pas échouer l'importation de l'employé si cette étape échoue.
                    }
                } else {
                    throw new \RuntimeException("Réponse invalide de l'API ERPNext lors de la création de l'employé");
                }
            } catch (\Throwable $e) {
                $errorCount++;
                $errorMessage = $this->sanitizeErrorMessage($e->getMessage());
                
                $this->logger->error('Échec de l\'importation de l\'employé', [
                    'ref' => $record['Ref'] ?? 'inconnu',
                    'error' => $errorMessage,
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Gestion spécifique des erreurs connues
                if ($this->isRetryableError($e)) {
                    $this->logger->info('Tentative de retry pour l\'employé', ['ref' => $record['Ref']]);
                    // Attendre un peu avant de continuer
                    usleep(500000); // 0.5 seconde
                    
                    try {
                        // Nouvelle tentative
                        $response = $this->erpNextService->addEmployee($employeeData);
                        if (isset($response['name'])) {
                            $employeeMap[$record['Ref']] = $response['name'];
                            $successCount++;
                            continue;
                        }
                    } catch (\Throwable $retryException) {
                        $this->logger->warning('Retry échoué pour l\'employé', [
                            'ref' => $record['Ref'],
                            'retry_error' => $retryException->getMessage()
                        ]);
                    }
                }
                
                // Ajoute un message flash d'avertissement, limité à 10 pour éviter le "flash flooding".
                if ($errorCount <= 10) {
                    $this->addFlash('warning', sprintf(
                        'Échec de l\'importation de l\'employé "%s": %s',
                        $record['Ref'] ?? 'inconnu',
                        $errorMessage
                    ));
                }
            }

            // Journalise la progression périodiquement.
            if ($processedCount % 10 === 0) {
                $this->logger->info('Mise à jour de la progression de l\'importation des employés', ['processed' => $processedCount, 'successful' => $successCount, 'errors' => $errorCount]);
            }
        }

        // Crée des listes de jours fériés par défaut pour toutes les entreprises rencontrées.
        foreach ($companies as $companyName) {
            try {
                $holidayListName = $companyName . " Holidays " . date('Y');
                $holidayList = $this->erpNextService->getHolidayList($holidayListName);

                if (empty($holidayList)) {
                    $this->erpNextService->createHolidayList($holidayListName, $companyName);
                }
                $this->erpNextService->setCompanyDefaultHolidayList($companyName, $holidayListName);

            } catch (\Throwable $e) {
                $this->logger->warning("Échec de la création/association de la liste de jours fériés pour l'entreprise", [
                    'company' => $companyName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Ajoute des messages flash de succès ou d'échec globaux pour l'importation des employés.
        if ($successCount > 0) {
            $this->addFlash('success', sprintf('%d employés importés avec succès.', $successCount));
        }

        if ($errorCount > 0) {
            $this->addFlash('danger', sprintf('%d employés n\'ont pas pu être importés (voir logs pour détails).', $errorCount));
        }

        return $employeeMap; // Retourne la carte des employés importés.
    }

    /**
     * Importe les structures salariales à partir d'un fichier CSV.
     *
     * @param UploadedFile $file Le fichier CSV contenant les données des structures salariales.
     * @param array $employeeMap Un tableau associatif mappant la référence de l'employé à son nom ERPNext.
     * @return void
     * @throws \RuntimeException Si un champ requis est manquant ou une valeur est invalide.
     */
    private function importSalaryStructures(UploadedFile $file, array $employeeMap): void
    {
        // Crée un lecteur CSV à partir du fichier téléchargé avec gestion de l'encodage.
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        
        // Détecte et configure l'encodage du fichier CSV
        $this->configureCsvEncoding($csv);
        
        // Définit la première ligne comme en-tête.
        $csv->setHeaderOffset(0);
        $structures = []; // Tableau pour regrouper les composants par structure salariale.
        $successCount = 0; // Compteur de structures salariales importées avec succès.
        $errorCount = 0;   // Compteur d'erreurs lors de l'importation des structures salariales.
        $processedCount = 0; // Compteur d'enregistrements traités.

        // Champs requis dans le fichier CSV des structures salariales.
        $requiredFields = ['salary structure', 'name', 'Abbr', 'type', 'valeur', 'company'];
        $headers = $csv->getHeader();
        // Vérifie la présence de tous les champs requis.
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier structures salariales - Champ requis manquant : $field");
            }
        }

        $records = iterator_to_array($csv->getRecords());
        $this->logger->info('Démarrage de l\'importation des structures salariales depuis le CSV', ['file' => $file->getClientOriginalName(), 'total_records' => count($records)]);

        // Première passe : regroupe les composants par structure salariale.
        foreach ($csv->getRecords() as $index => $record) {
            $processedCount++;
            // Vérifie si la limite de traitement par lot est atteinte.
            if ($processedCount > self::BATCH_LIMIT) {
                $this->logger->warning('Limite de lot atteinte pour l\'importation des structures salariales', ['limit' => self::BATCH_LIMIT]);
                $this->addFlash('warning', sprintf('Limite de traitement atteinte. Seuls les %d premiers composants de structure salariale ont été importés.', self::BATCH_LIMIT));
                break; // Arrête le traitement si la limite est atteinte.
            }

            try {
                $this->logger->debug('Traitement de l\'enregistrement de la structure salariale', ['line' => $index + 2, 'structure' => $record['salary structure'] ?? 'inconnu']);
                // Vérifie que tous les champs requis ont une valeur.
                foreach ($requiredFields as $field) {
                    if (!isset($record[$field]) || trim($record[$field]) === '') {
                        throw new \RuntimeException("Valeur manquante pour le champ '$field' à la ligne " . ($index + 2));
                    }
                }
                $companyName = trim($record['company']);
                if ($companyName === '') {
                    throw new \RuntimeException("Nom de l'entreprise vide à la ligne " . ($index + 2));
                }

                // S'assure que l'entreprise existe dans ERPNext.
                $this->ensureCompanyExists($companyName);

                // Ajoute le composant à la structure correspondante.
                $structures[$record['salary structure']]['components'][] = $record;
                $structures[$record['salary structure']]['company'] = $companyName;
            } catch (\Throwable $e) {
                $errorCount++;
                $this->logger->error('Erreur lors du traitement du composant de structure salariale', [
                    'line' => $index + 2,
                    'error' => $e->getMessage()
                ]);
                // Ajoute un message flash d'avertissement, limité à 10.
                if ($errorCount <= 10) {
                    $this->addFlash('warning', sprintf(
                        'Erreur lors du traitement du composant à la ligne %d: %s',
                        $index + 2,
                        $e->getMessage()
                    ));
                }
            }

            // Journalise la progression périodiquement.
            if ($processedCount % 10 === 0) {
                $this->logger->info('Mise à jour de la progression de l\'importation des structures salariales', ['processed' => $processedCount, 'errors' => $errorCount]);
            }
        }

        // Seconde passe : crée les composants et les structures salariales dans ERPNext.
        foreach ($structures as $name => $data) {
            try {
                $componentSuccessCount = 0;
                $componentErrorCount = 0;
                // Crée ou met à jour chaque composant de salaire.
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
                        $this->logger->warning('Échec de la création/mise à jour du composant', [
                            'component' => $component['name'],
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }

                $earnings = [];
                $deductions = [];
                // Sépare les composants en gains et déductions.
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
                // Prépare les données de la structure salariale.
                $structureData = [
                    'doctype' => 'Salary Structure',
                    'name' => $name,
                    'company' => $data['company'],
                    'earnings' => $earnings,
                    'deductions' => $deductions,
                    'is_active' => 'Yes',
                    'payment_account' => null, // Peut être défini si nécessaire.
                ];

                // Sauvegarde la structure salariale dans ERPNext.
                $this->erpNextService->saveSalaryStructure($structureData);

                // Assigne la structure salariale aux employés si la carte des employés n'est pas vide.
                if (!empty($employeeMap)) {
                    foreach ($employeeMap as $employeeNumber => $employeeId) {
                        try {
                            $fromDate = date('Y-m-01'); // Date de début d'assignation (premier jour du mois courant).
                            $this->erpNextService->assignSalaryStructure($employeeId, $name, $fromDate);
                        } catch (\Throwable $e) {
                            $this->logger->warning("Échec de l'assignation de la structure salariale à l'employé", [
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
                $this->logger->error('Échec de l\'importation de la structure salariale', [
                    'structure' => $name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Ajoute un message flash d'avertissement, limité à 10.
                if ($errorCount <= 10) {
                    $this->addFlash('warning', sprintf(
                        'Échec de l\'importation de la structure salariale "%s": %s',
                        $name,
                        $e->getMessage()
                    ));
                }
            }
        }

        // Ajoute des messages flash de succès ou d'échec globaux pour l'importation des structures salariales.
        if ($successCount > 0) {
            $this->addFlash('success', sprintf('%d structures salariales importées avec succès.', $successCount));
        }

        if ($errorCount > 0) {
            $this->addFlash('danger', sprintf('%d structures salariales n\'ont pas pu être importées. Consultez les logs pour le détail.', $errorCount));
        }
    }

    /**
     * Importe les données salariales (fiches de paie) à partir d'un fichier CSV.
     *
     * @param UploadedFile $file Le fichier CSV contenant les données salariales.
     * @param array $employeeMap Un tableau associatif mappant la référence de l'employé à son nom ERPNext.
     * @return void
     * @throws \RuntimeException Si un champ requis est manquant, une valeur est invalide ou l'employé/structure est introuvable.
     */
    private function importSalaryData(UploadedFile $file, array $employeeMap): void
    {
        // Crée un lecteur CSV à partir du fichier téléchargé avec gestion de l'encodage.
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        
        // Détecte et configure l'encodage du fichier CSV
        $this->configureCsvEncoding($csv);
        
        // Définit la première ligne comme en-tête.
        $csv->setHeaderOffset(0);
        $successCount = 0; // Compteur de fiches de paie importées avec succès.
        $errorCount = 0;   // Compteur d'erreurs lors de l'importation des fiches de paie.
        $processedCount = 0; // Compteur d'enregistrements traités.

        // Champs requis dans le fichier CSV des données salariales.
        $requiredFields = ['Mois', 'Ref Employe', 'Salaire Base', 'Salaire'];
        $headers = $csv->getHeader();
        // Vérifie la présence de tous les champs requis.
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                throw new \RuntimeException("Fichier données salariales - Champ requis manquant : $field");
            }
        }

        $records = iterator_to_array($csv->getRecords());
        $this->logger->info('Démarrage de l\'importation des données salariales depuis le CSV', ['file' => $file->getClientOriginalName(), 'total_records' => count($records)]);

        // Parcourt chaque enregistrement du CSV.
        foreach ($csv->getRecords() as $index => $record) {
            $processedCount++;
            // Vérifie si la limite de traitement par lot est atteinte.
            if ($processedCount > self::BATCH_LIMIT) {
                $this->logger->warning('Limite de lot atteinte pour l\'importation des données salariales', ['limit' => self::BATCH_LIMIT]);
                $this->addFlash('warning', sprintf('Limite de traitement atteinte. Seuls les %d premiers enregistrements de salaire ont été importés.', self::BATCH_LIMIT));
                break; // Arrête le traitement si la limite est atteinte.
            }

            try {
                $this->logger->debug('Traitement de l\'enregistrement des données salariales', ['line' => $index + 2, 'employee_ref' => $record['Ref Employe'] ?? 'inconnu']);
                // Vérifie que tous les champs requis ont une valeur.
                foreach ($requiredFields as $field) {
                    if (!isset($record[$field]) || trim($record[$field]) === '') {
                        throw new \RuntimeException("Valeur manquante pour le champ '$field' à la ligne " . ($index + 2));
                    }
                }

                $employeeRef = $record['Ref Employe'];
                $employeeId = null;

                // Tente de récupérer l'ID de l'employé à partir de la carte ou de l'API ERPNext.
                if (!isset($employeeMap[$employeeRef])) {
                    $existingEmployee = $this->erpNextService->getEmployeeByNumber($employeeRef);
                    if (!empty($existingEmployee)) {
                        $employeeId = $existingEmployee[0]['name'];
                        $employeeMap[$employeeRef] = $employeeId; // Met à jour la carte pour les futures utilisations.
                    } else {
                        throw new \Exception("Employé avec référence '$employeeRef' introuvable");
                    }
                } else {
                    $employeeId = $employeeMap[$employeeRef];
                }

                // Valide l'existence de la structure salariale dans ERPNext.
                $structureName = $record['Salaire'];
                $salaryStructure = $this->erpNextService->getSalaryStructure($structureName);
                if (empty($salaryStructure)) {
                    throw new \RuntimeException("Structure salariale '$structureName' introuvable");
                }

                // Analyse la date du mois, supportant les formats JJ/MM/AAAA et MM/AAAA.
                try {
                    $startDate = \DateTime::createFromFormat('d/m/Y', $record['Mois']);
                    if (!$startDate) {
                        $startDate = \DateTime::createFromFormat('m/Y', $record['Mois']);
                        if (!$startDate) {
                            throw new \Exception("Format de date invalide");
                        }
                        // Si le format est MM/AAAA, définit le jour au 1er du mois.
                        $year = (int)$startDate->format('Y');
                        $month = (int)$startDate->format('m');
                        $startDate->setDate($year, $month, 1);
                    }
                    // Calcule la date de fin (dernier jour du mois).
                    $endDate = (clone $startDate)->modify('last day of this month');
                } catch (\Throwable $e) {
                    throw new \RuntimeException("Format de date invalide pour '{$record['Mois']}': " . $e->getMessage());
                }

                // Assigne la structure salariale avec une date antérieure à la période de paie
                // pour s'assurer qu'elle est applicable.
                $assignmentDate = (clone $startDate)->modify('first day of previous month');
                
                $this->logger->info("Assignation de la structure salariale à l'employé", [
                    'employee' => $employeeId,
                    'structure' => $structureName,
                    'from_date' => $assignmentDate->format('Y-m-d'),
                    'salary_period' => $startDate->format('Y-m-d') . ' au ' . $endDate->format('Y-m-d')
                ]);
                
                try {
                    // Appelle l'API pour assigner la structure salariale.
                    $startTime = microtime(true);
                    $this->erpNextService->assignSalaryStructure(
                        $employeeId,
                        $structureName,
                        $assignmentDate->format('Y-m-d')
                    );
                    $this->logger->debug('Appel API pour assigner la structure salariale terminé', ['employee' => $employeeId, 'duration' => microtime(true) - $startTime]);
                    
                    // Attente pour s'assurer que l'assignation est bien enregistrée dans ERPNext.
                    sleep(2);
                    
                    // Vérifie que l'assignation a bien été effectuée.
                    $assignment = $this->erpNextService->getEmployeeSalaryStructureAssignment(
                        $employeeId,
                        $startDate->format('Y-m-d')
                    );
                    
                    if (!$assignment) {
                        throw new \RuntimeException("L'assignation de la structure salariale n'a pas été correctement enregistrée");
                    }
                    
                    $this->logger->info("Structure salariale assignée avec succès", [
                        'employee' => $employeeId,
                        'structure' => $structureName,
                        'assignment' => $assignment['name']
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->warning("Échec de l'assignation de la structure salariale, mais continuation du processus", [
                        'employee' => $employeeId,
                        'structure' => $structureName,
                        'error' => $e->getMessage()
                    ]);
                }

                // Analyse le montant du salaire de base.
                $baseAmount = $this->parseSalaryAmount($record['Salaire Base']);
                
                // Met à jour l'assignation de structure salariale avec le montant de base correct.
                try {
                    $this->erpNextService->updateSalaryStructureAssignmentBase(
                        $employeeId,
                        $structureName,
                        $assignmentDate->format('Y-m-d'),
                        $baseAmount
                    );
                    $this->logger->info("Montant de base de l'assignation de structure salariale mis à jour", [
                        'employee' => $employeeId,
                        'structure' => $structureName,
                        'base_amount' => $baseAmount
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->warning("Échec de la mise à jour du montant de base de l'assignation de structure salariale", [
                        'employee' => $employeeId,
                        'structure' => $structureName,
                        'error' => $e->getMessage()
                    ]);
                }

                // Prépare les données de la fiche de paie.
                $salaryData = [
                    'employee' => $employeeId,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'posting_date' => date('Y-m-d'), // Date de publication (aujourd'hui).
                    'salary_structure' => $structureName,
                    'base' => $baseAmount
                ];

                // Ajoute la fiche de paie dans ERPNext.
                $startTime = microtime(true);
                $this->erpNextService->addSalarySlip($salaryData);
                $this->logger->debug('Appel API pour ajouter la fiche de paie terminé', ['employee' => $employeeId, 'duration' => microtime(true) - $startTime]);
                $successCount++;
            } catch (\Throwable $e) {
                $errorCount++;
                $errorMessage = $this->sanitizeErrorMessage($e->getMessage());
                
                $this->logger->error('Échec de l\'importation de la fiche de paie', [
                    'employee_ref' => $record['Ref Employe'] ?? 'inconnu',
                    'month' => $record['Mois'] ?? 'inconnu',
                    'error' => $errorMessage,
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Gestion spécifique des erreurs de fiches de paie
                if ($this->isCancelledDocumentError($e)) {
                    $this->logger->info('Document annulé détecté, passage au suivant', [
                        'employee_ref' => $record['Ref Employe'] ?? 'inconnu'
                    ]);
                    continue; // Passer au suivant sans compter comme erreur
                }
                
                if ($this->isRetryableError($e)) {
                    $this->logger->info('Tentative de retry pour la fiche de paie', [
                        'employee_ref' => $record['Ref Employe'] ?? 'inconnu'
                    ]);
                    
                    // Attendre avant retry
                    sleep(1);
                    
                    try {
                        // Nouvelle tentative
                        $this->erpNextService->addSalarySlip($salaryData);
                        $successCount++;
                        continue;
                    } catch (\Throwable $retryException) {
                        $this->logger->warning('Retry échoué pour la fiche de paie', [
                            'employee_ref' => $record['Ref Employe'] ?? 'inconnu',
                            'retry_error' => $retryException->getMessage()
                        ]);
                    }
                }
                
                if ($errorCount <= 10) {
                    $this->addFlash('warning', sprintf(
                        'Échec de l\'importation de la fiche de paie pour l\'employé "%s" (mois %s): %s',
                        $record['Ref Employe'] ?? 'inconnu',
                        $record['Mois'] ?? 'inconnu',
                        $errorMessage
                    ));
                }
            }

            // Journalise la progression périodiquement.
            if ($processedCount % 10 === 0) {
                $this->logger->info('Mise à jour de la progression de l\'importation des données salariales', ['processed' => $processedCount, 'successful' => $successCount, 'errors' => $errorCount]);
            }
        }

        // Ajoute des messages flash de succès ou d'échec globaux pour l'importation des fiches de paie.
        if ($successCount > 0) {
            $this->addFlash('success', sprintf('%d fiches de paie importées avec succès.', $successCount));
        }
        if ($errorCount > 0) {
            $this->addFlash('danger', sprintf('%d fiches de paie n\'ont pas pu être importées. Consultez les logs pour détails.', $errorCount));
        }
    }

    /**
     * Convertit une date du format JJ/MM/AAAA vers le format AAAA-MM-JJ
     *
     * @param string $date Date au format JJ/MM/AAAA
     * @return string Date au format AAAA-MM-JJ
     * @throws \InvalidArgumentException Si le format de date est invalide
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
     * Vérifie et crée si nécessaire une entreprise dans ERPNext.
     *
     * @param string $companyName Le nom de l'entreprise à vérifier/créer
     * @return void
     * @throws \RuntimeException Si la création de l'entreprise échoue
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
     * Analyse et convertit un montant salarial dans différents formats numériques.
     *
     * @param string $amount Le montant à analyser (peut contenir des espaces, virgules, etc.)
     * @return float Le montant converti en float
     * @throws \InvalidArgumentException Si le montant n'est pas valide
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
     * Évalue de manière sécurisée des formules mathématiques (acceptant des identifiants comme "SB").
     *
     * @param string $formula La formule mathématique à évaluer
     * @return float Le résultat numérique de l'évaluation
     * @throws \InvalidArgumentException Si la formule contient des caractères invalides ou une syntaxe incorrecte
     *
     * @warning Ne doit être utilisé qu'avec des formules mathématiques simples
     *          et des données pré-vérifiées pour éviter les injections de code.
     *          Les variables doivent être prédéfinies dans le code.
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

    /**
     * Analyse les fichiers CSV et retourne des informations détaillées sur leur contenu.
     *
     * @param UploadedFile $employeeFile Fichier CSV des employés.
     * @param UploadedFile $structureFile Fichier CSV des structures salariales.
     * @param UploadedFile $dataFile Fichier CSV des données salariales.
     * @return array Informations d'analyse des fichiers.
     * @throws \RuntimeException Si l'analyse échoue.
     */
    private function analyzeFiles(UploadedFile $employeeFile, UploadedFile $structureFile, UploadedFile $dataFile): array
    {
        $analysis = [
            'employees' => $this->analyzeEmployeeFile($employeeFile),
            'structures' => $this->analyzeStructureFile($structureFile),
            'salary_data' => $this->analyzeSalaryDataFile($dataFile),
            'total_records' => 0,
            'analysis_time' => date('Y-m-d H:i:s'),
            'estimated_duration' => 0,
            'warnings' => [],
        ];

        $analysis['total_records'] = $analysis['employees']['record_count'] + 
                                   $analysis['structures']['record_count'] + 
                                   $analysis['salary_data']['record_count'];

        // Estimation du temps de traitement (environ 2 secondes par 100 enregistrements)
        $analysis['estimated_duration'] = max(1, ceil($analysis['total_records'] / 50));

        // Analyse des relations entre fichiers
        $analysis['cross_file_analysis'] = $this->analyzeCrossFileRelations($analysis);

        return $analysis;
    }

    /**
     * Analyse les relations entre les différents fichiers CSV.
     *
     * @param array $analysis Données d'analyse des fichiers individuels.
     * @return array Informations sur les relations entre fichiers.
     */
    private function analyzeCrossFileRelations(array $analysis): array
    {
        $crossAnalysis = [
            'employee_structure_match' => 0,
            'employee_salary_match' => 0,
            'structure_salary_match' => 0,
            'orphaned_employees' => [],
            'orphaned_structures' => [],
            'missing_employees' => [],
            'warnings' => [],
        ];

        // Vérifie la correspondance entre employés et données salariales
        $employeeRefs = array_map('trim', $analysis['salary_data']['employees'] ?? []);
        $salaryStructures = array_map('trim', $analysis['salary_data']['structures'] ?? []);
        $definedStructures = array_keys($analysis['structures']['structures'] ?? []);

        // Employés dans les données salariales mais pas dans le fichier employés
        $crossAnalysis['missing_employees'] = array_diff($employeeRefs, 
            array_column($analysis['employees']['companies'] ?? [], 'ref'));

        // Structures utilisées dans les données salariales mais pas définies
        $crossAnalysis['orphaned_structures'] = array_diff($salaryStructures, $definedStructures);

        // Calcul des correspondances
        $crossAnalysis['employee_salary_match'] = count($employeeRefs) - count($crossAnalysis['missing_employees']);
        $crossAnalysis['structure_salary_match'] = count($salaryStructures) - count($crossAnalysis['orphaned_structures']);

        // Génération des avertissements
        if (!empty($crossAnalysis['missing_employees'])) {
            $crossAnalysis['warnings'][] = sprintf(
                "%d employé(s) référencé(s) dans les données salariales ne sont pas définis dans le fichier employés",
                count($crossAnalysis['missing_employees'])
            );
        }

        if (!empty($crossAnalysis['orphaned_structures'])) {
            $crossAnalysis['warnings'][] = sprintf(
                "%d structure(s) salariale(s) utilisée(s) dans les données ne sont pas définies",
                count($crossAnalysis['orphaned_structures'])
            );
        }

        return $crossAnalysis;
    }

    /**
     * Analyse le fichier CSV des employés.
     *
     * @param UploadedFile $file Fichier CSV des employés.
     * @return array Informations d'analyse du fichier.
     * @throws \RuntimeException Si l'analyse échoue.
     */
    private function analyzeEmployeeFile(UploadedFile $file): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        
        $requiredFields = ['Ref', 'Nom', 'Prenom', 'genre', 'Date embauche', 'date naissance', 'company'];
        $headers = $csv->getHeader();
        $records = iterator_to_array($csv->getRecords());
        
        // Vérifie les champs requis
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                $missingFields[] = $field;
            }
        }

        // Analyse les entreprises uniques et les employés
        $companies = [];
        $companiesDetails = []; // Nouveau : détails par entreprise
        $validRecords = 0;
        $invalidRecords = 0;
        $errors = [];
        $genderStats = ['M' => 0, 'F' => 0, 'Autre' => 0]; // Nouveau : statistiques par genre

        foreach ($records as $index => $record) {
            $isValid = true;
            $recordErrors = [];

            // Vérifie les champs requis
            foreach ($requiredFields as $field) {
                if (!isset($record[$field]) || trim($record[$field]) === '') {
                    $recordErrors[] = "Champ '$field' manquant";
                    $isValid = false;
                }
            }

            // Vérifie le format des dates
            if (isset($record['Date embauche']) && !empty($record['Date embauche'])) {
                try {
                    $this->convertDate($record['Date embauche']);
                } catch (\Exception $e) {
                    $recordErrors[] = "Format de date d'embauche invalide";
                    $isValid = false;
                }
            }

            if (isset($record['date naissance']) && !empty($record['date naissance'])) {
                try {
                    $this->convertDate($record['date naissance']);
                } catch (\Exception $e) {
                    $recordErrors[] = "Format de date de naissance invalide";
                    $isValid = false;
                }
            }

            if ($isValid) {
                $validRecords++;
                $companyName = $record['company'];
                
                // Comptage par entreprise
                if (!in_array($companyName, $companies)) {
                    $companies[] = $companyName;
                    $companiesDetails[$companyName] = [
                        'employee_count' => 0,
                        'employees' => []
                    ];
                }
                $companiesDetails[$companyName]['employee_count']++;
                $companiesDetails[$companyName]['employees'][] = [
                    'ref' => $record['Ref'],
                    'name' => $record['Nom'] . ' ' . $record['Prenom'],
                    'gender' => $record['genre']
                ];
                
                // Statistiques par genre
                $gender = strtoupper(trim($record['genre']));
                if ($gender === 'M' || $gender === 'MASCULIN' || $gender === 'HOMME') {
                    $genderStats['M']++;
                } elseif ($gender === 'F' || $gender === 'FEMININ' || $gender === 'FEMME') {
                    $genderStats['F']++;
                } else {
                    $genderStats['Autre']++;
                }
            } else {
                $invalidRecords++;
                if (count($errors) < 10) { // Limite les erreurs affichées
                    $errors[] = "Ligne " . ($index + 2) . ": " . implode(', ', $recordErrors);
                }
            }
        }

        return [
            'filename' => $file->getClientOriginalName(),
            'record_count' => count($records),
            'valid_records' => $validRecords,
            'invalid_records' => $invalidRecords,
            'missing_fields' => $missingFields,
            'companies' => $companies,
            'errors' => $errors,
            'headers' => $headers,
        ];
    }

    /**
     * Analyse le fichier CSV des structures salariales.
     *
     * @param UploadedFile $file Fichier CSV des structures salariales.
     * @return array Informations d'analyse du fichier.
     * @throws \RuntimeException Si l'analyse échoue.
     */
    private function analyzeStructureFile(UploadedFile $file): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        
        $requiredFields = ['salary structure', 'name', 'Abbr', 'type', 'valeur', 'company'];
        $headers = $csv->getHeader();
        $records = iterator_to_array($csv->getRecords());
        
        // Vérifie les champs requis
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                $missingFields[] = $field;
            }
        }

        // Analyse les structures et composants
        $structures = [];
        $companies = [];
        $validRecords = 0;
        $invalidRecords = 0;
        $errors = [];

        foreach ($records as $index => $record) {
            $isValid = true;
            $recordErrors = [];

            // Vérifie les champs requis
            foreach ($requiredFields as $field) {
                if (!isset($record[$field]) || trim($record[$field]) === '') {
                    $recordErrors[] = "Champ '$field' manquant";
                    $isValid = false;
                }
            }

            if ($isValid) {
                $validRecords++;
                $structureName = $record['salary structure'];
                if (!isset($structures[$structureName])) {
                    $structures[$structureName] = [
                        'components' => 0,
                        'earnings' => 0,
                        'deductions' => 0,
                    ];
                }
                $structures[$structureName]['components']++;
                
                if (strtolower($record['type']) === 'earning') {
                    $structures[$structureName]['earnings']++;
                } else {
                    $structures[$structureName]['deductions']++;
                }

                if (!in_array($record['company'], $companies)) {
                    $companies[] = $record['company'];
                }
            } else {
                $invalidRecords++;
                if (count($errors) < 10) {
                    $errors[] = "Ligne " . ($index + 2) . ": " . implode(', ', $recordErrors);
                }
            }
        }

        return [
            'filename' => $file->getClientOriginalName(),
            'record_count' => count($records),
            'valid_records' => $validRecords,
            'invalid_records' => $invalidRecords,
            'missing_fields' => $missingFields,
            'structures' => $structures,
            'companies' => $companies,
            'errors' => $errors,
            'headers' => $headers,
        ];
    }

    /**
     * Analyse le fichier CSV des données salariales.
     *
     * @param UploadedFile $file Fichier CSV des données salariales.
     * @return array Informations d'analyse du fichier.
     * @throws \RuntimeException Si l'analyse échoue.
     */
    private function analyzeSalaryDataFile(UploadedFile $file): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        
        $requiredFields = ['Mois', 'Ref Employe', 'Salaire Base', 'Salaire'];
        $headers = $csv->getHeader();
        $records = iterator_to_array($csv->getRecords());
        
        // Vérifie les champs requis
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!in_array($field, $headers)) {
                $missingFields[] = $field;
            }
        }

        // Analyse les données salariales
        $employees = [];
        $employeeSalaries = []; // Nouveau : comptage des salaires par employé
        $months = [];
        $structures = [];
        $validRecords = 0;
        $invalidRecords = 0;
        $errors = [];
        $totalSalaryAmount = 0; // Nouveau : montant total des salaires
        $salaryStats = []; // Nouveau : statistiques des salaires

        foreach ($records as $index => $record) {
            $isValid = true;
            $recordErrors = [];

            // Vérifie les champs requis
            foreach ($requiredFields as $field) {
                if (!isset($record[$field]) || trim($record[$field]) === '') {
                    $recordErrors[] = "Champ '$field' manquant";
                    $isValid = false;
                }
            }

            // Vérifie le format de la date
            if (isset($record['Mois']) && !empty($record['Mois'])) {
                try {
                    $startDate = \DateTime::createFromFormat('d/m/Y', $record['Mois']);
                    if (!$startDate) {
                        $startDate = \DateTime::createFromFormat('m/Y', $record['Mois']);
                        if (!$startDate) {
                            $recordErrors[] = "Format de date invalide pour le mois";
                            $isValid = false;
                        }
                    }
                } catch (\Exception $e) {
                    $recordErrors[] = "Format de date invalide pour le mois";
                    $isValid = false;
                }
            }

            // Vérifie le montant du salaire de base
            $salaryAmount = 0;
            if (isset($record['Salaire Base']) && !empty($record['Salaire Base'])) {
                try {
                    $salaryAmount = $this->parseSalaryAmount($record['Salaire Base']);
                } catch (\Exception $e) {
                    $recordErrors[] = "Montant de salaire de base invalide";
                    $isValid = false;
                }
            }

            if ($isValid) {
                $validRecords++;
                $employeeRef = $record['Ref Employe'];
                
                // Comptage des salaires par employé
                if (!isset($employeeSalaries[$employeeRef])) {
                    $employeeSalaries[$employeeRef] = [
                        'count' => 0,
                        'total_amount' => 0,
                        'months' => []
                    ];
                }
                $employeeSalaries[$employeeRef]['count']++;
                $employeeSalaries[$employeeRef]['total_amount'] += $salaryAmount;
                $employeeSalaries[$employeeRef]['months'][] = $record['Mois'];
                
                if (!in_array($employeeRef, $employees)) {
                    $employees[] = $employeeRef;
                }
                if (!in_array($record['Mois'], $months)) {
                    $months[] = $record['Mois'];
                }
                if (!in_array($record['Salaire'], $structures)) {
                    $structures[] = $record['Salaire'];
                }
                
                $totalSalaryAmount += $salaryAmount;
            } else {
                $invalidRecords++;
                if (count($errors) < 10) {
                    $errors[] = "Ligne " . ($index + 2) . ": " . implode(', ', $recordErrors);
                }
            }
        }

        // Calcul des statistiques salariales
        if (!empty($employeeSalaries)) {
            $amounts = array_column($employeeSalaries, 'total_amount');
            $salaryStats = [
                'min_salary' => min($amounts),
                'max_salary' => max($amounts),
                'avg_salary' => array_sum($amounts) / count($amounts),
                'total_amount' => $totalSalaryAmount,
            ];
        }

        return [
            'filename' => $file->getClientOriginalName(),
            'record_count' => count($records),
            'valid_records' => $validRecords,
            'invalid_records' => $invalidRecords,
            'missing_fields' => $missingFields,
            'employees' => $employees,
            'employee_salaries' => $employeeSalaries, // Nouveau
            'salary_stats' => $salaryStats, // Nouveau
            'months' => $months,
            'structures' => $structures,
            'errors' => $errors,
            'headers' => $headers,
        ];
    }

    /**
     * Compte le nombre de lignes dans un fichier CSV (sans l'en-tête).
     *
     * @param UploadedFile $file Le fichier CSV.
     * @return int Le nombre de lignes de données.
     */
    private function getCsvLineCount(UploadedFile $file): int
    {
        if (!$file) {
            return 0;
        }

        try {
            $csv = Reader::createFromPath($file->getPathname(), 'r');
            $csv->setHeaderOffset(0);
            return count(iterator_to_array($csv->getRecords()));
        } catch (\Exception $e) {
            $this->logger->warning('Erreur lors du comptage des lignes CSV', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Nettoie un répertoire temporaire et tous ses fichiers.
     *
     * @param string $tempDir Le chemin du répertoire temporaire à nettoyer.
     * @return void
     */
    private function cleanupTempDirectory(string $tempDir): void
    {
        try {
            if (!is_dir($tempDir)) {
                return;
            }

            // Supprime tous les fichiers du répertoire
            $files = glob($tempDir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            // Supprime le répertoire lui-même
            rmdir($tempDir);
            
            $this->logger->debug('Répertoire temporaire nettoyé', ['temp_dir' => $tempDir]);
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur lors du nettoyage du répertoire temporaire', [
                'temp_dir' => $tempDir,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Configure l'encodage du fichier CSV pour gérer les caractères spéciaux.
     *
     * @param Reader $csv Le lecteur CSV à configurer
     * @return void
     */
    private function configureCsvEncoding(Reader $csv): void
    {
        try {
            // Tente de détecter l'encodage du fichier
            $sample = $csv->fetchOne();
            if ($sample) {
                $encoding = mb_detect_encoding(implode('', $sample), ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
                if ($encoding && $encoding !== 'UTF-8') {
                    $this->logger->info('Encodage détecté', ['encoding' => $encoding]);
                    // Note: League\Csv ne supporte pas directement la conversion d'encodage
                    // Il faudrait préprocesser le fichier si nécessaire
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur lors de la détection d\'encodage', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Nettoie et sécurise un message d'erreur pour l'affichage.
     *
     * @param string $message Le message d'erreur brut
     * @return string Le message nettoyé
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Supprime les informations sensibles ou techniques
        $message = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[EMAIL]', $message);
        $message = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[IP]', $message);
        $message = preg_replace('/password["\s]*[:=]["\s]*[^"\s,}]+/i', 'password: [HIDDEN]', $message);
        
        // Limite la longueur du message
        if (strlen($message) > 200) {
            $message = substr($message, 0, 197) . '...';
        }
        
        return $message;
    }

    /**
     * Détermine si une erreur peut être réessayée.
     *
     * @param \Throwable $exception L'exception à analyser
     * @return bool True si l'erreur peut être réessayée
     */
    private function isRetryableError(\Throwable $exception): bool
    {
        $message = $exception->getMessage();
        
        // Erreurs de concurrence/timing
        if (stripos($message, 'TimestampMismatchError') !== false) {
            return true;
        }
        
        // Erreurs de connexion temporaires
        if (stripos($message, 'Connection timeout') !== false ||
            stripos($message, 'Connection refused') !== false ||
            stripos($message, 'Temporary failure') !== false) {
            return true;
        }
        
        // Erreurs de verrouillage de base de données
        if (stripos($message, 'Lock wait timeout') !== false ||
            stripos($message, 'Deadlock found') !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Détermine si une erreur concerne un document annulé.
     *
     * @param \Throwable $exception L'exception à analyser
     * @return bool True si l'erreur concerne un document annulé
     */
    private function isCancelledDocumentError(\Throwable $exception): bool
    {
        $message = $exception->getMessage();
        
        return stripos($message, 'Cannot edit cancelled document') !== false ||
               stripos($message, 'Cannot update cancelled salary slip') !== false ||
               stripos($message, 'Document is cancelled') !== false;
    }

    /**
     * Valide les données d'un employé avant l'import.
     *
     * @param array $employeeData Les données de l'employé
     * @return array Liste des erreurs de validation
     */
    private function validateEmployeeData(array $employeeData): array
    {
        $errors = [];
        
        // Validation du numéro d'employé
        if (empty($employeeData['employee_number']) || !is_string($employeeData['employee_number'])) {
            $errors[] = 'Numéro d\'employé requis et doit être une chaîne';
        }
        
        // Validation du nom
        if (empty($employeeData['first_name']) || empty($employeeData['last_name'])) {
            $errors[] = 'Prénom et nom requis';
        }
        
        // Validation du genre
        if (!empty($employeeData['gender']) && !in_array($employeeData['gender'], ['Male', 'Female'])) {
            $errors[] = 'Genre doit être "Male" ou "Female"';
        }
        
        // Validation des dates
        if (!empty($employeeData['date_of_joining'])) {
            if (!\DateTime::createFromFormat('Y-m-d', $employeeData['date_of_joining'])) {
                $errors[] = 'Format de date d\'embauche invalide (attendu: YYYY-MM-DD)';
            }
        }
        
        if (!empty($employeeData['date_of_birth'])) {
            if (!\DateTime::createFromFormat('Y-m-d', $employeeData['date_of_birth'])) {
                $errors[] = 'Format de date de naissance invalide (attendu: YYYY-MM-DD)';
            }
        }
        
        return $errors;
    }

    /**
     * Améliore la gestion des timeouts pour les imports longs.
     *
     * @return void
     */
    private function configureTimeouts(): void
    {
        // Augmente les limites de temps pour les imports longs
        set_time_limit(300); // 5 minutes
        ini_set('memory_limit', '512M');
        
        // Configure les timeouts HTTP si possible
        if (function_exists('ini_set')) {
            ini_set('default_socket_timeout', 60);
        }
    }
}