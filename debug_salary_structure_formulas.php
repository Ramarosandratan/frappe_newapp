<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\ErpNextService;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;

// Configuration ERPNext
$apiBase = $_ENV['API_BASE'] ?? 'http://localhost:8000';
$apiKey = $_ENV['API_KEY'] ?? '';
$apiSecret = $_ENV['API_SECRET'] ?? '';

if (empty($apiKey) || empty($apiSecret)) {
    echo "❌ Configuration ERPNext manquante. Veuillez définir API_KEY et API_SECRET\n";
    exit(1);
}

// Initialisation du service ERPNext
$client = HttpClient::create();
$logger = new NullLogger();
$erpNextService = new ErpNextService($client, $logger, $apiBase, $apiKey, $apiSecret);

echo "🔍 Diagnostic des structures salariales et formules\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    // 1. Vérifier la structure salariale g1
    echo "1. Vérification de la structure salariale 'g1':\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    $structure = $erpNextService->getSalaryStructure('g1');
    if (!$structure) {
        echo "❌ Structure salariale 'g1' introuvable\n";
        echo "💡 Veuillez créer la structure salariale 'g1' d'abord\n\n";
        
        // Lister les structures disponibles
        echo "📋 Structures salariales disponibles:\n";
        $structures = $erpNextService->getSalaryStructures();
        foreach ($structures as $struct) {
            echo "   - {$struct['name']}\n";
        }
        exit(1);
    }
    
    echo "✅ Structure 'g1' trouvée\n";
    echo "   - Statut: " . ($structure['docstatus'] ?? 'N/A') . "\n";
    echo "   - Entreprise: " . ($structure['company'] ?? 'N/A') . "\n";
    echo "   - Active: " . ($structure['is_active'] ?? 'N/A') . "\n\n";
    
    // 2. Analyser les composants de gains (earnings)
    echo "2. Analyse des composants de gains:\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    $usedAbbreviations = [];
    $missingComponents = [];
    
    if (isset($structure['earnings']) && is_array($structure['earnings'])) {
        echo "📊 Composants de gains trouvés: " . count($structure['earnings']) . "\n";
        
        foreach ($structure['earnings'] as $earning) {
            $componentName = $earning['salary_component'] ?? 'N/A';
            $formula = $earning['formula'] ?? '';
            $condition = $earning['condition'] ?? '';
            
            echo "   • {$componentName}\n";
            if (!empty($formula)) {
                echo "     Formule: {$formula}\n";
                
                // Extraire les abréviations utilisées dans la formule
                if (preg_match_all('/\b[A-Z]{2,}\b/', $formula, $matches)) {
                    foreach ($matches[0] as $abbr) {
                        $usedAbbreviations[$abbr] = true;
                    }
                }
            }
            if (!empty($condition)) {
                echo "     Condition: {$condition}\n";
                
                // Extraire les abréviations utilisées dans la condition
                if (preg_match_all('/\b[A-Z]{2,}\b/', $condition, $matches)) {
                    foreach ($matches[0] as $abbr) {
                        $usedAbbreviations[$abbr] = true;
                    }
                }
            }
        }
    } else {
        echo "⚠️  Aucun composant de gains trouvé\n";
    }
    
    echo "\n";
    
    // 3. Analyser les composants de déductions
    echo "3. Analyse des composants de déductions:\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    if (isset($structure['deductions']) && is_array($structure['deductions'])) {
        echo "📊 Composants de déductions trouvés: " . count($structure['deductions']) . "\n";
        
        foreach ($structure['deductions'] as $deduction) {
            $componentName = $deduction['salary_component'] ?? 'N/A';
            $formula = $deduction['formula'] ?? '';
            $condition = $deduction['condition'] ?? '';
            
            echo "   • {$componentName}\n";
            if (!empty($formula)) {
                echo "     Formule: {$formula}\n";
                
                // Extraire les abréviations utilisées dans la formule
                if (preg_match_all('/\b[A-Z]{2,}\b/', $formula, $matches)) {
                    foreach ($matches[0] as $abbr) {
                        $usedAbbreviations[$abbr] = true;
                    }
                }
            }
            if (!empty($condition)) {
                echo "     Condition: {$condition}\n";
                
                // Extraire les abréviations utilisées dans la condition
                if (preg_match_all('/\b[A-Z]{2,}\b/', $condition, $matches)) {
                    foreach ($matches[0] as $abbr) {
                        $usedAbbreviations[$abbr] = true;
                    }
                }
            }
        }
    } else {
        echo "⚠️  Aucun composant de déductions trouvé\n";
    }
    
    echo "\n";
    
    // 4. Vérifier les abréviations utilisées
    echo "4. Vérification des abréviations utilisées:\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    if (!empty($usedAbbreviations)) {
        echo "📝 Abréviations trouvées dans les formules:\n";
        foreach (array_keys($usedAbbreviations) as $abbr) {
            echo "   - {$abbr}\n";
        }
        echo "\n";
        
        // Vérifier si ces composants existent
        echo "🔍 Vérification de l'existence des composants:\n";
        foreach (array_keys($usedAbbreviations) as $abbr) {
            $component = $erpNextService->getSalaryComponent($abbr);
            if ($component) {
                echo "   ✅ {$abbr}: " . ($component['salary_component'] ?? 'N/A') . "\n";
            } else {
                echo "   ❌ {$abbr}: COMPOSANT MANQUANT\n";
                $missingComponents[] = $abbr;
            }
        }
    } else {
        echo "ℹ️  Aucune abréviation trouvée dans les formules\n";
    }
    
    echo "\n";
    
    // 5. Résumé et recommandations
    echo "5. Résumé et recommandations:\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    if (!empty($missingComponents)) {
        echo "❌ PROBLÈME IDENTIFIÉ:\n";
        echo "   Les composants suivants sont référencés dans les formules mais n'existent pas:\n";
        foreach ($missingComponents as $abbr) {
            echo "   • {$abbr}\n";
        }
        echo "\n";
        echo "💡 SOLUTIONS RECOMMANDÉES:\n";
        echo "   1. Créer les composants manquants avec les bonnes abréviations\n";
        echo "   2. Ou modifier les formules pour utiliser les abréviations correctes\n";
        echo "   3. Vérifier que tous les composants sont bien assignés à la structure\n\n";
        
        // Lister tous les composants disponibles
        echo "📋 Composants de salaire disponibles:\n";
        $allComponents = $erpNextService->getSalaryComponents();
        foreach ($allComponents as $comp) {
            $name = $comp['name'] ?? 'N/A';
            $abbr = $comp['salary_component_abbr'] ?? 'N/A';
            $type = $comp['type'] ?? 'N/A';
            echo "   - {$name} (Abbr: {$abbr}, Type: {$type})\n";
        }
        
    } else {
        echo "✅ Aucun problème détecté avec les abréviations\n";
        echo "   Toutes les abréviations utilisées dans les formules correspondent à des composants existants\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erreur lors du diagnostic: " . $e->getMessage() . "\n";
    echo "📍 Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Diagnostic terminé\n";