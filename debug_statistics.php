<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\ErpNextService;
use Symfony\Component\HttpClient\HttpClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Configuration pour ERPNext (à adapter selon votre environnement)
$erpNextUrl = 'http://localhost:8000'; // Remplacez par votre URL ERPNext
$apiKey = 'your-api-key';
$apiSecret = 'your-api-secret';

try {
    // Créer un logger pour voir les détails
    $logger = new Logger('debug');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
    
    $httpClient = HttpClient::create();
    $erpNextService = new ErpNextService($httpClient, $logger);
    $erpNextService->setCredentials($erpNextUrl, $apiKey, $apiSecret);
    
    echo "🔍 Diagnostic des Statistiques de Fiches de Paie\n\n";
    
    // 1. Vérifier les fiches de paie du mois en cours
    $currentMonth = date('Y-m-01');
    $endOfMonth = date('Y-m-t');
    $currentYear = date('Y');
    
    echo "📅 Période analysée: $currentMonth à $endOfMonth\n";
    echo "📅 Année courante: $currentYear\n";
    echo "=" . str_repeat("=", 60) . "\n";
    
    // Méthode utilisée par HomeController
    echo "🏠 Méthode HomeController (getSalarySlipsByPeriod):\n";
    try {
        $currentMonthSlips = $erpNextService->getSalarySlipsByPeriod($currentMonth, $endOfMonth);
        echo "✅ Trouvé " . count($currentMonthSlips) . " fiche(s) de paie\n";
        
        if (!empty($currentMonthSlips)) {
            echo "📋 Détails des fiches trouvées:\n";
            foreach (array_slice($currentMonthSlips, 0, 5) as $slip) { // Afficher les 5 premières
                echo "   - " . ($slip['name'] ?? 'N/A') . 
                     " | Employé: " . ($slip['employee_name'] ?? 'N/A') . 
                     " | Période: " . ($slip['start_date'] ?? 'N/A') . " à " . ($slip['end_date'] ?? 'N/A') .
                     " | Net: " . ($slip['net_pay'] ?? 0) . "€\n";
            }
            if (count($currentMonthSlips) > 5) {
                echo "   ... et " . (count($currentMonthSlips) - 5) . " autre(s)\n";
            }
        }
    } catch (\Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n";
    
    // 2. Vérifier avec la méthode getSalarySlips (plus générale)
    echo "🔍 Méthode alternative (getSalarySlips):\n";
    try {
        $allSlips = $erpNextService->getSalarySlips([
            'start_date' => $currentMonth,
            'end_date' => $endOfMonth,
        ]);
        echo "✅ Trouvé " . count($allSlips) . " fiche(s) de paie\n";
        
        if (!empty($allSlips)) {
            echo "📋 Détails des fiches trouvées:\n";
            foreach (array_slice($allSlips, 0, 5) as $slip) {
                echo "   - " . ($slip['name'] ?? 'N/A') . 
                     " | Employé: " . ($slip['employee'] ?? 'N/A') . 
                     " | Période: " . ($slip['start_date'] ?? 'N/A') . " à " . ($slip['end_date'] ?? 'N/A') . "\n";
            }
        }
    } catch (\Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n";
    
    // 3. Vérifier toutes les fiches de paie récentes (derniers 30 jours)
    echo "📊 Toutes les fiches des 30 derniers jours:\n";
    try {
        $last30Days = date('Y-m-d', strtotime('-30 days'));
        $today = date('Y-m-d');
        
        $recentSlips = $erpNextService->getSalarySlips([
            'start_date' => $last30Days,
        ]);
        
        echo "✅ Trouvé " . count($recentSlips) . " fiche(s) de paie depuis $last30Days\n";
        
        if (!empty($recentSlips)) {
            // Grouper par mois
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
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n";
    
    // 4. Vérifier avec la méthode utilisée par StatsController
    echo "📊 Méthode StatsController (getAllSalarySlips):\n";
    try {
        $allSlipsForYear = $erpNextService->getAllSalarySlips($currentYear);
        echo "✅ Trouvé " . count($allSlipsForYear) . " fiche(s) de paie pour l'année $currentYear\n";
        
        if (!empty($allSlipsForYear)) {
            echo "📋 Détails des fiches trouvées:\n";
            foreach (array_slice($allSlipsForYear, 0, 3) as $slip) {
                echo "   - " . ($slip['name'] ?? 'N/A') . 
                     " | Employé: " . ($slip['employee_name'] ?? $slip['employee'] ?? 'N/A') . 
                     " | Période: " . ($slip['start_date'] ?? 'N/A') . " à " . ($slip['end_date'] ?? 'N/A') .
                     " | Net: " . ($slip['net_pay'] ?? 0) . "€\n";
                
                // Vérifier si les détails sont complets
                if (isset($slip['earnings']) && is_array($slip['earnings'])) {
                    echo "     Gains: " . count($slip['earnings']) . " composant(s)\n";
                } else {
                    echo "     ⚠️ Aucun détail de gains trouvé\n";
                }
                
                if (isset($slip['deductions']) && is_array($slip['deductions'])) {
                    echo "     Déductions: " . count($slip['deductions']) . " composant(s)\n";
                } else {
                    echo "     ⚠️ Aucun détail de déductions trouvé\n";
                }
            }
            if (count($allSlipsForYear) > 3) {
                echo "   ... et " . (count($allSlipsForYear) - 3) . " autre(s)\n";
            }
        }
    } catch (\Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n";
    
    // 5. Vérifier les employés actifs
    echo "👥 Employés actifs:\n";
    try {
        $employees = $erpNextService->getActiveEmployees();
        echo "✅ Trouvé " . count($employees) . " employé(s) actif(s)\n";
    } catch (\Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    // 5. Vérifier les structures salariales
    echo "🏗️ Structures salariales:\n";
    try {
        $structures = $erpNextService->getSalaryStructures();
        echo "✅ Trouvé " . count($structures) . " structure(s) salariale(s)\n";
    } catch (\Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎯 Recommandations:\n";
    
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
    echo "❌ Erreur générale: " . $e->getMessage() . "\n";
    echo "📝 Trace: " . $e->getTraceAsString() . "\n";
}