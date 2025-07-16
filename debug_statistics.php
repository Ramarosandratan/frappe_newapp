<?php

// Inclut l'autoloader de Composer pour charger les dépendances du projet.
require_once __DIR__ . '/vendor/autoload.php';

// Importe les classes nécessaires depuis leurs namespaces respectifs.
use App\Service\ErpNextService; // Service pour interagir avec l'API ERPNext.
use Symfony\Component\HttpClient\HttpClient; // Client HTTP pour effectuer des requêtes.
use Monolog\Logger; // Classe pour la journalisation (logging).
use Monolog\Handler\StreamHandler; // Handler pour écrire les logs dans un flux (ici, la console).

// Section de configuration pour l'accès à l'API ERPNext.
// Ces valeurs doivent être adaptées à votre environnement ERPNext.
$erpNextUrl = 'http://localhost:8000'; // URL de base de votre instance ERPNext.
$apiKey = 'your-api-key'; // Clé API pour l'authentification.
$apiSecret = 'your-api-secret'; // Secret API correspondant à la clé.

try {
    // Initialisation du logger.
    // Crée une instance de Logger nommée 'debug'.
    $logger = new Logger('debug');
    // Ajoute un handler pour diriger les messages de log (à partir du niveau DEBUG) vers la sortie standard (console).
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
    
    // Initialisation du client HTTP et du service ERPNext.
    $httpClient = HttpClient::create(); // Crée une instance du client HTTP.
    // Instancie le service ERPNext en lui passant le client HTTP et le logger.
    $erpNextService = new ErpNextService($httpClient, $logger);
    // Définit les identifiants de connexion (URL, clé API, secret API) pour le service ERPNext.
    $erpNextService->setCredentials($erpNextUrl, $apiKey, $apiSecret);
    
    // Affiche un titre pour le diagnostic.
    echo "🔍 Diagnostic des Statistiques de Fiches de Paie\n\n";
    
    // 1. Vérification des fiches de paie pour le mois en cours.
    // Définit les dates de début et de fin pour le mois actuel.
    $currentMonth = date('Y-m-01'); // Premier jour du mois courant.
    $endOfMonth = date('Y-m-t'); // Dernier jour du mois courant.
    $currentYear = date('Y'); // Année courante.
    
    // Affiche la période et l'année analysées.
    echo "📅 Période analysée: $currentMonth à $endOfMonth\n";
    echo "📅 Année courante: $currentYear\n";
    echo "=" . str_repeat("=", 60) . "\n";
    
    // Test de la méthode getSalarySlipsByPeriod (utilisée par HomeController).
    echo "🏠 Méthode HomeController (getSalarySlipsByPeriod):\n";
    try {
        // Tente de récupérer les fiches de paie pour le mois en cours.
        $currentMonthSlips = $erpNextService->getSalarySlipsByPeriod($currentMonth, $endOfMonth);
        // Affiche le nombre de fiches trouvées.
        echo "✅ Trouvé " . count($currentMonthSlips) . " fiche(s) de paie\n";
        
        // Si des fiches sont trouvées, affiche les détails des 5 premières.
        if (!empty($currentMonthSlips)) {
            echo "📋 Détails des fiches trouvées:\n";
            foreach (array_slice($currentMonthSlips, 0, 5) as $slip) { // Affiche les 5 premières fiches.
                echo "   - " . ($slip['name'] ?? 'N/A') . 
                     " | Employé: " . ($slip['employee_name'] ?? 'N/A') . 
                     " | Période: " . ($slip['start_date'] ?? 'N/A') . " à " . ($slip['end_date'] ?? 'N/A') .
                     " | Net: " . ($slip['net_pay'] ?? 0) . "€\n";
            }
            // Indique s'il y a plus de 5 fiches.
            if (count($currentMonthSlips) > 5) {
                echo "   ... et " . (count($currentMonthSlips) - 5) . " autre(s)\n";
            }
        }
    } catch (\Exception $e) {
        // Capture et affiche les erreurs spécifiques à cet appel.
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n";
    
    // 2. Vérification avec la méthode getSalarySlips (plus générale).
    echo "🔍 Méthode alternative (getSalarySlips):\n";
    try {
        // Tente de récupérer les fiches de paie en utilisant des filtres de date.
        $allSlips = $erpNextService->getSalarySlips([
            'start_date' => $currentMonth,
            'end_date' => $endOfMonth,
        ]);
        // Affiche le nombre de fiches trouvées.
        echo "✅ Trouvé " . count($allSlips) . " fiche(s) de paie\n";
        
        // Si des fiches sont trouvées, affiche les détails des 5 premières.
        if (!empty($allSlips)) {
            echo "📋 Détails des fiches trouvées:\n";
            foreach (array_slice($allSlips, 0, 5) as $slip) {
                echo "   - " . ($slip['name'] ?? 'N/A') . 
                     " | Employé: " . ($slip['employee'] ?? 'N/A') . 
                     " | Période: " . ($slip['start_date'] ?? 'N/A') . " à " . ($slip['end_date'] ?? 'N/A') . "\n";
            }
        }
    } catch (\Exception $e) {
        // Capture et affiche les erreurs spécifiques à cet appel.
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n";
    
    // 3. Vérification de toutes les fiches de paie récentes (derniers 30 jours).
    echo "📊 Toutes les fiches des 30 derniers jours:\n";
    try {
        // Calcule la date il y a 30 jours et la date d'aujourd'hui.
        $last30Days = date('Y-m-d', strtotime('-30 days'));
        $today = date('Y-m-d');
        
        // Récupère les fiches de paie à partir de la date calculée.
        $recentSlips = $erpNextService->getSalarySlips([
            'start_date' => $last30Days,
        ]);
        
        // Affiche le nombre de fiches trouvées pour cette période.
        echo "✅ Trouvé " . count($recentSlips) . " fiche(s) de paie depuis $last30Days\n";
        
        // Si des fiches sont trouvées, les groupe par mois et affiche le décompte.
        if (!empty($recentSlips)) {
            $slipsByMonth = [];
            foreach ($recentSlips as $slip) {
                $startDate = $slip['start_date'] ?? '';
                if ($startDate) {
                    $month = date('Y-m', strtotime($startDate));
                    if (!isset($slipsByMonth[$month])) {
                        $slipsByMonth[$month] = [];
                    }
                    $slipsByMonth[$month][] = $slip;
                }
            }
            
            echo "📅 Répartition par mois:\n";
            foreach ($slipsByMonth as $month => $slips) {
                echo "   - $month: " . count($slips) . " fiche(s)\n";
            }
        }
    } catch (\Exception $e) {
        // Capture et affiche les erreurs spécifiques à cet appel.
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n";
    
    // 4. Vérification avec la méthode utilisée par StatsController.
    echo "📊 Méthode StatsController (getAllSalarySlips):\n";
    try {
        // Récupère toutes les fiches de paie pour l'année courante.
        $allSlipsForYear = $erpNextService->getAllSalarySlips($currentYear);
        // Affiche le nombre de fiches trouvées.
        echo "✅ Trouvé " . count($allSlipsForYear) . " fiche(s) de paie pour l'année $currentYear\n";
        
        // Si des fiches sont trouvées, affiche les détails des 3 premières et vérifie la complétude.
        if (!empty($allSlipsForYear)) {
            echo "📋 Détails des fiches trouvées:\n";
            foreach (array_slice($allSlipsForYear, 0, 3) as $slip) {
                echo "   - " . ($slip['name'] ?? 'N/A') . 
                     " | Employé: " . ($slip['employee_name'] ?? $slip['employee'] ?? 'N/A') . 
                     " | Période: " . ($slip['start_date'] ?? 'N/A') . " à " . ($slip['end_date'] ?? 'N/A') .
                     " | Net: " . ($slip['net_pay'] ?? 0) . "€\n";
                
                // Vérifie si les détails des gains sont présents.
                if (isset($slip['earnings']) && is_array($slip['earnings'])) {
                    echo "     Gains: " . count($slip['earnings']) . " composant(s)\n";
                } else {
                    echo "     ⚠️ Aucun détail de gains trouvé\n";
                }
                
                // Vérifie si les détails des déductions sont présents.
                if (isset($slip['deductions']) && is_array($slip['deductions'])) {
                    echo "     Déductions: " . count($slip['deductions']) . " composant(s)\n";
                } else {
                    echo "     ⚠️ Aucun détail de déductions trouvé\n";
                }
            }
            // Indique s'il y a plus de 3 fiches.
            if (count($allSlipsForYear) > 3) {
                echo "   ... et " . (count($allSlipsForYear) - 3) . " autre(s)\n";
            }
        }
    } catch (\Exception $e) {
        // Capture et affiche les erreurs spécifiques à cet appel.
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n";
    
    // 5. Vérification des employés actifs.
    echo "👥 Employés actifs:\n";
    try {
        // Récupère la liste des employés actifs.
        $employees = $erpNextService->getActiveEmployees();
        // Affiche le nombre d'employés actifs trouvés.
        echo "✅ Trouvé " . count($employees) . " employé(s) actif(s)\n";
    } catch (\Exception $e) {
        // Capture et affiche les erreurs spécifiques à cet appel.
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    // 6. Vérification des structures salariales.
    echo "🏗️ Structures salariales:\n";
    try {
        // Récupère la liste des structures salariales.
        $structures = $erpNextService->getSalaryStructures();
        // Affiche le nombre de structures salariales trouvées.
        echo "✅ Trouvé " . count($structures) . " structure(s) salariale(s)\n";
    } catch (\Exception $e) {
        // Capture et affiche les erreurs spécifiques à cet appel.
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎯 Recommandations:\n";
    
    // Section de recommandations basée sur les résultats du diagnostic.
    if (empty($currentMonthSlips)) {
        echo "⚠️ Aucune fiche de paie trouvée pour le mois en cours ($currentMonth à $endOfMonth)\n";
        echo "💡 Vérifiez que:\n";
        echo "   1. Les fiches ont été générées pour la bonne période\n";
        echo "   2. Les dates de début/fin correspondent exactement\n";
        echo "   3. Les fiches sont bien soumises dans ERPNext\n";
        echo "   4. L'utilisateur API a les permissions de lecture\n";
    } else {
        echo "✅ Les fiches de paie sont présentes et devraient s'afficher\n";
        echo "💡 Si elles ne s'affichent pas, vérifiez:\n";
        echo "   1. Le cache du navigateur\n";
        echo "   2. Les erreurs JavaScript dans la console\n";
        echo "   3. Les logs Symfony pour d'autres erreurs\n";
    }
    
} catch (Exception $e) {
    // Bloc de capture d'erreurs générales pour toute exception non gérée précédemment.
    echo "❌ Erreur générale: " . $e->getMessage() . "\n";
    echo "📝 Trace: " . $e->getTraceAsString() . "\n";
}