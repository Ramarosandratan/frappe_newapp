<?php

/**
 * Script de diagnostic pour les erreurs de soumission des fiches de paie
 * Analyse les logs et identifie les problèmes courants
 */

echo "=== Diagnostic des erreurs de soumission ===\n\n";

// Fonction pour analyser les logs
function analyzeLogs($logFile) {
    if (!file_exists($logFile)) {
        echo "❌ Fichier de log non trouvé: $logFile\n";
        return;
    }
    
    echo "📋 Analyse du fichier: $logFile\n";
    
    $content = file_get_contents($logFile);
    $lines = explode("\n", $content);
    
    $errors = [];
    $warnings = [];
    $salarySlipErrors = [];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        // Rechercher les erreurs liées aux fiches de paie
        if (stripos($line, 'salary slip') !== false || stripos($line, 'salary_slip') !== false) {
            if (stripos($line, 'ERROR') !== false) {
                $salarySlipErrors[] = $line;
            } elseif (stripos($line, 'WARNING') !== false) {
                $warnings[] = $line;
            }
        }
        
        // Rechercher les erreurs de soumission spécifiques
        if (stripos($line, 'submit') !== false && stripos($line, 'ERROR') !== false) {
            $errors[] = $line;
        }
        
        // Rechercher les erreurs de validation
        if (stripos($line, 'ValidationError') !== false || stripos($line, 'TimestampMismatchError') !== false) {
            $errors[] = $line;
        }
    }
    
    echo "  🔍 Erreurs de fiches de paie trouvées: " . count($salarySlipErrors) . "\n";
    echo "  ⚠️  Avertissements trouvés: " . count($warnings) . "\n";
    echo "  ❌ Erreurs de soumission trouvées: " . count($errors) . "\n\n";
    
    // Afficher les dernières erreurs
    if (!empty($salarySlipErrors)) {
        echo "  📄 Dernières erreurs de fiches de paie:\n";
        foreach (array_slice($salarySlipErrors, -5) as $error) {
            echo "    " . substr($error, 0, 150) . "...\n";
        }
        echo "\n";
    }
    
    if (!empty($errors)) {
        echo "  🚨 Dernières erreurs de soumission:\n";
        foreach (array_slice($errors, -3) as $error) {
            echo "    " . substr($error, 0, 150) . "...\n";
        }
        echo "\n";
    }
    
    return [
        'salary_slip_errors' => count($salarySlipErrors),
        'warnings' => count($warnings),
        'submission_errors' => count($errors)
    ];
}

// Analyser les logs principaux
$logFiles = [
    '/home/rina/frappe_newapp/var/log/dev.log',
    '/home/rina/frappe_newapp/var/log/prod.log',
    '/home/rina/frappe_newapp/server.log'
];

$totalErrors = 0;
foreach ($logFiles as $logFile) {
    $result = analyzeLogs($logFile);
    if ($result) {
        $totalErrors += $result['salary_slip_errors'] + $result['submission_errors'];
    }
}

// Vérifications de configuration
echo "🔧 Vérifications de configuration:\n\n";

// 1. Vérifier les variables d'environnement ERPNext
$envFile = '/home/rina/frappe_newapp/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    $hasErpNextConfig = strpos($envContent, 'ERPNEXT_API_BASE') !== false;
    echo "  ✓ Fichier .env trouvé\n";
    echo "  " . ($hasErpNextConfig ? "✓" : "❌") . " Configuration ERPNext présente\n";
} else {
    echo "  ❌ Fichier .env non trouvé\n";
}

// 2. Vérifier la structure des services
$serviceFiles = [
    '/home/rina/frappe_newapp/src/Service/ErpNextService.php',
    '/home/rina/frappe_newapp/src/Service/MonthlyPercentageService.php',
    '/home/rina/frappe_newapp/src/Controller/SalaryModifierController.php'
];

echo "\n📁 Vérification des fichiers de service:\n";
foreach ($serviceFiles as $file) {
    $exists = file_exists($file);
    echo "  " . ($exists ? "✓" : "❌") . " " . basename($file) . "\n";
    
    if ($exists) {
        $content = file_get_contents($file);
        $hasValidation = strpos($content, 'validateSalarySlipTotals') !== false || 
                        strpos($content, 'validateSalarySlipData') !== false;
        echo "    " . ($hasValidation ? "✓" : "⚠️ ") . " Méthodes de validation présentes\n";
    }
}

// 3. Problèmes courants identifiés
echo "\n🔍 Problèmes courants identifiés:\n\n";

$commonIssues = [
    "Totaux incohérents" => "Les totaux (gross_pay, total_deduction, net_pay) ne correspondent pas aux composants individuels",
    "Erreurs de timestamp" => "TimestampMismatchError lors de la soumission - problème de concurrence",
    "Validation ERPNext" => "ERPNext rejette la soumission à cause de données invalides",
    "Pourcentages extrêmes" => "Application de pourcentages trop élevés ou trop bas",
    "Valeurs négatives" => "Calculs résultant en valeurs négatives non autorisées"
];

foreach ($commonIssues as $issue => $description) {
    echo "  📌 $issue:\n";
    echo "     $description\n\n";
}

// Solutions recommandées
echo "💡 Solutions recommandées:\n\n";

$solutions = [
    "1. Validation des données" => [
        "Utiliser validateSalarySlipTotals() avant chaque sauvegarde",
        "Vérifier la cohérence des montants avec une tolérance de 0.01",
        "S'assurer que tous les champs obligatoires sont présents"
    ],
    "2. Gestion des erreurs" => [
        "Implémenter un retry avec délai pour les TimestampMismatchError",
        "Logger toutes les erreurs avec le contexte complet",
        "Continuer le traitement même si une fiche échoue"
    ],
    "3. Optimisation des pourcentages" => [
        "Limiter les pourcentages entre -100% et +1000%",
        "Valider les mois (1-12) et les valeurs de base (>= 0)",
        "Arrondir les résultats à 2 décimales"
    ],
    "4. Tests et monitoring" => [
        "Exécuter validate_monthly_percentages.php régulièrement",
        "Surveiller les logs pour les patterns d'erreur",
        "Tester avec un petit échantillon avant application massive"
    ]
];

foreach ($solutions as $category => $items) {
    echo "  $category:\n";
    foreach ($items as $item) {
        echo "    • $item\n";
    }
    echo "\n";
}

// Commandes utiles
echo "🛠️  Commandes utiles pour le débogage:\n\n";
echo "  # Voir les dernières erreurs de fiches de paie\n";
echo "  tail -f var/log/dev.log | grep -i 'salary.*slip'\n\n";
echo "  # Rechercher les erreurs de soumission\n";
echo "  grep -i 'submit.*error' var/log/dev.log | tail -10\n\n";
echo "  # Valider la logique des pourcentages\n";
echo "  php validate_monthly_percentages.php\n\n";
echo "  # Vérifier la base de données\n";
echo "  php bin/console doctrine:schema:validate\n\n";

if ($totalErrors > 0) {
    echo "⚠️  $totalErrors erreurs détectées dans les logs. Consultez les détails ci-dessus.\n";
} else {
    echo "✅ Aucune erreur majeure détectée dans les logs récents.\n";
}

echo "\n=== Diagnostic terminé ===\n";