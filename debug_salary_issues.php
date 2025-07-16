<?php

// Inclusion de l'autoloader de Composer pour charger les dépendances
require_once __DIR__ . '/vendor/autoload.php';

// Importation des classes nécessaires depuis leurs namespaces
use App\Service\ErpNextService; // Service pour interagir avec l'API ERPNext
use Symfony\Component\HttpClient\HttpClient; // Client HTTP pour les requêtes API
use Monolog\Logger; // Classe pour la journalisation (logging)
use Monolog\Handler\StreamHandler; // Handler pour écrire les logs dans un flux (ici, la console)

// Configuration pour la connexion à l'instance ERPNext
// Ces valeurs doivent être adaptées à votre environnement ERPNext
$erpNextUrl = 'http://localhost:8000'; // URL de base de votre instance ERPNext
$apiKey = 'your-api-key'; // Clé API pour l'authentification
$apiSecret = 'your-api-secret'; // Secret API pour l'authentification

// Bloc try-catch global pour capturer les exceptions non gérées et afficher un message d'erreur général
try {
    // Initialisation du logger Monolog
    // Un logger est créé avec le nom 'debug'
    $logger = new Logger('debug');
    // Un handler est ajouté pour diriger les messages de log vers la sortie standard (console)
    // Le niveau de log est défini sur DEBUG, ce qui signifie que tous les messages (DEBUG, INFO, WARNING, ERROR, etc.) seront affichés
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
    
    // Création d'une instance du client HTTP de Symfony
    $httpClient = HttpClient::create();
    // Instanciation du service ERPNext, en lui passant le client HTTP et le logger
    $erpNextService = new ErpNextService($httpClient, $logger);
    // Définition des identifiants de connexion (URL, clé API, secret API) pour le service ERPNext
    $erpNextService->setCredentials($erpNextUrl, $apiKey, $apiSecret);
    
    // Affichage d'un message d'introduction pour le diagnostic
    echo "🔍 Diagnostic des problèmes de génération de salaire\n\n";
    
    // Définition des IDs des employés à diagnostiquer
    // Ces employés sont considérés comme "problématiques" et seront analysés en détail
    $problematicEmployees = ['HR-EMP-00029', 'HR-EMP-00030'];
    
    // Boucle sur chaque employé problématique pour effectuer un diagnostic individuel
    foreach ($problematicEmployees as $employeeId) {
        echo "👤 Analyse de l'employé: $employeeId\n";
        echo "=" . str_repeat("=", 50) . "\n"; // Ligne de séparation pour la lisibilité
        
        // Bloc try-catch pour gérer les erreurs spécifiques à chaque employé
        try {
            // 1. Récupération des détails de l'employé depuis ERPNext
            $employee = $erpNextService->getEmployee($employeeId);
            if ($employee) {
                echo "✅ Employé trouvé:\n";
                // Affichage des informations clés de l'employé
                echo "   - Nom: " . ($employee['employee_name'] ?? 'N/A') . "\n";
                echo "   - Statut: " . ($employee['status'] ?? 'N/A') . "\n";
                echo "   - Société: " . ($employee['company'] ?? 'N/A') . "\n";
                // Affichage de tous les champs disponibles pour l'employé, utile pour le débogage
                echo "   - Champs disponibles: " . implode(', ', array_keys($employee)) . "\n";
                
                // Vérification des champs de salaire potentiels dans les détails de l'employé
                $salaryFields = ['salary_rate', 'basic_salary', 'base_salary', 'monthly_salary'];
                $foundSalary = false;
                foreach ($salaryFields as $field) {
                    // Si un champ de salaire est trouvé et sa valeur est supérieure à 0
                    if (isset($employee[$field]) && $employee[$field] > 0) {
                        echo "   - $field: " . $employee[$field] . "\n";
                        $foundSalary = true;
                    }
                }
                // Si aucun champ de salaire valide n'est trouvé
                if (!$foundSalary) {
                    echo "   ⚠️ Aucun champ de salaire trouvé dans les détails de l'employé\n";
                }
            } else {
                echo "❌ Employé non trouvé\n";
            }
            
            // 2. Vérification de l'assignation de structure salariale pour l'employé
            echo "\n📋 Assignation de structure salariale:\n";
            // Récupération de l'assignation pour une date donnée (ici, 1er janvier 2024)
            $assignment = $erpNextService->getEmployeeSalaryStructureAssignment($employeeId, '2024-01-01');
            if ($assignment) {
                echo "✅ Assignation trouvée:\n";
                // Affichage des détails de l'assignation
                echo "   - Structure: " . ($assignment['salary_structure'] ?? 'N/A') . "\n";
                echo "   - Base: " . ($assignment['base'] ?? 'N/A') . "\n";
                echo "   - Date début: " . ($assignment['from_date'] ?? 'N/A') . "\n";
                // Affichage de tous les champs disponibles pour l'assignation
                echo "   - Champs disponibles: " . implode(', ', array_keys($assignment)) . "\n";
            } else {
                echo "❌ Aucune assignation de structure salariale trouvée\n";
            }
            
            // 3. Vérification des fiches de paie existantes pour l'employé et la période donnée
            echo "\n💰 Fiches de paie existantes:\n";
            // Récupération des fiches de paie pour l'employé et le mois de janvier 2024
            $existingSlips = $erpNextService->getSalarySlips([
                'employee' => $employeeId,
                'start_date' => '2024-01-01',
                'end_date' => '2024-01-31',
            ]);
            
            if (!empty($existingSlips)) {
                echo "✅ " . count($existingSlips) . " fiche(s) trouvée(s):\n";
                // Boucle sur chaque fiche de paie trouvée
                foreach ($existingSlips as $slip) {
                    echo "   - " . ($slip['name'] ?? 'N/A') . " (statut: " . ($slip['docstatus'] ?? 'N/A') . ")\n";
                    
                    // Essai de récupération des détails complets de chaque fiche de paie
                    try {
                        $slipDetails = $erpNextService->getSalarySlipDetails($slip['name']);
                        if ($slipDetails) {
                            echo "     Base: " . ($slipDetails['base'] ?? 'N/A') . "\n";
                            echo "     Gains: " . count($slipDetails['earnings'] ?? []) . " composant(s)\n";
                            echo "     Déductions: " . count($slipDetails['deductions'] ?? []) . " composant(s)\n";
                        }
                    } catch (\Exception $e) {
                        echo "     ❌ Erreur récupération détails: " . $e->getMessage() . "\n";
                    }
                }
            } else {
                echo "ℹ️ Aucune fiche de paie existante pour janvier 2024\n";
            }
            
            // 4. Test de suppression d'une fiche de paie existante (pour le débogage)
            // Cette section est utile pour tester la capacité à supprimer des fiches de paie,
            // ce qui peut être nécessaire pour refaire des tests de génération.
            if (!empty($existingSlips)) {
                $firstSlip = $existingSlips[0]; // Prend la première fiche trouvée
                echo "\n🗑️ Test de suppression de la fiche: " . $firstSlip['name'] . "\n";
                
                try {
                    $deleteResult = $erpNextService->deleteSalarySlip($firstSlip['name']);
                    if ($deleteResult) {
                        echo "✅ Suppression réussie\n";
                    } else {
                        echo "❌ Suppression échouée\n";
                    }
                } catch (\Exception $e) {
                    echo "❌ Erreur lors de la suppression: " . $e->getMessage() . "\n";
                }
            }
            
        } catch (\Exception $e) {
            // Capture et affichage des erreurs spécifiques à l'analyse d'un employé
            echo "❌ Erreur lors de l'analyse: " . $e->getMessage() . "\n";
        }
        
        echo "\n" . str_repeat("-", 60) . "\n\n"; // Ligne de séparation après chaque employé
    }
    
    // 5. Vérification des structures salariales disponibles dans ERPNext
    echo "🏗️ Structures salariales disponibles:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    try {
        $structures = $erpNextService->getSalaryStructures();
        if (!empty($structures)) {
            echo "✅ " . count($structures) . " structure(s) trouvée(s):\n";
            // Affichage des noms de toutes les structures salariales trouvées
            foreach ($structures as $structure) {
                echo "   - " . ($structure['name'] ?? 'N/A') . "\n";
            }
        } else {
            echo "❌ Aucune structure salariale trouvée\n";
        }
    } catch (\Exception $e) {
        // Capture et affichage des erreurs lors de la récupération des structures salariales
        echo "❌ Erreur récupération structures: " . $e->getMessage() . "\n";
    }
    
    // Section des recommandations basées sur les diagnostics effectués
    echo "\n🎯 Recommandations:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    echo "1. Vérifiez que les employés ont une assignation de structure salariale active\n";
    echo "2. Vérifiez que les structures salariales ont un montant de base défini\n";
    echo "3. Si la suppression échoue, vérifiez les permissions dans ERPNext\n";
    echo "4. Consultez les logs détaillés pour plus d'informations\n";
    
} catch (Exception $e) {
    // Capture et affichage des erreurs générales qui n'ont pas été gérées par les blocs try-catch internes
    echo "❌ Erreur générale: " . $e->getMessage() . "\n";
    echo "📝 Trace: " . $e->getTraceAsString() . "\n"; // Affichage de la trace de la pile pour un débogage approfondi
}