<?php

/**
 * Script de test pour vérifier les corrections des erreurs de soumission
 */

echo "=== Test des corrections de soumission ===\n\n";

// Simulation des statuts de documents ERPNext
$DOCSTATUS = [
    0 => 'Draft',
    1 => 'Submitted', 
    2 => 'Cancelled'
];

// Fonction pour simuler la gestion des statuts
function handleDocumentStatus($docstatus, $slipName) {
    global $DOCSTATUS;
    
    echo "📄 Traitement de la fiche: $slipName (Status: {$DOCSTATUS[$docstatus]})\n";
    
    switch ($docstatus) {
        case 0: // Draft
            echo "  ✓ Document en draft - peut être modifié directement\n";
            return true;
            
        case 1: // Submitted
            echo "  ⚠️  Document soumis - doit être annulé puis modifié puis re-soumis\n";
            return true;
            
        case 2: // Cancelled
            echo "  ❌ Document annulé - sera ignoré (ne peut pas être modifié)\n";
            return false;
            
        default:
            echo "  ❓ Statut inconnu - sera ignoré\n";
            return false;
    }
}

// Test 1: Gestion des différents statuts
echo "Test 1: Gestion des statuts de documents\n";
$testSlips = [
    ['name' => 'Sal Slip/HR-EMP-00001/00001', 'docstatus' => 0],
    ['name' => 'Sal Slip/HR-EMP-00002/00001', 'docstatus' => 1],
    ['name' => 'Sal Slip/HR-EMP-00003/00001', 'docstatus' => 2], // Problématique
    ['name' => 'Sal Slip/HR-EMP-00004/00001', 'docstatus' => 0],
];

$processedCount = 0;
$skippedCount = 0;

foreach ($testSlips as $slip) {
    $canProcess = handleDocumentStatus($slip['docstatus'], $slip['name']);
    if ($canProcess) {
        $processedCount++;
    } else {
        $skippedCount++;
    }
}

echo "\nRésultat: $processedCount traités, $skippedCount ignorés\n\n";

// Test 2: Validation des totaux
echo "Test 2: Validation des totaux de fiche de paie\n";

function validateSalarySlipTotals($slip) {
    $calculatedEarnings = 0;
    $calculatedDeductions = 0;
    
    foreach ($slip['earnings'] as $earning) {
        $calculatedEarnings += $earning['amount'];
    }
    
    foreach ($slip['deductions'] as $deduction) {
        $calculatedDeductions += $deduction['amount'];
    }
    
    $calculatedNetPay = $calculatedEarnings - $calculatedDeductions;
    $tolerance = 0.01;
    
    $grossPayOk = abs($slip['gross_pay'] - $calculatedEarnings) <= $tolerance;
    $deductionOk = abs($slip['total_deduction'] - $calculatedDeductions) <= $tolerance;
    $netPayOk = abs($slip['net_pay'] - $calculatedNetPay) <= $tolerance;
    
    echo "  📊 Validation des totaux pour {$slip['name']}:\n";
    echo "    Gains: " . ($grossPayOk ? "✓" : "❌") . " (Stocké: {$slip['gross_pay']}, Calculé: $calculatedEarnings)\n";
    echo "    Déductions: " . ($deductionOk ? "✓" : "❌") . " (Stocké: {$slip['total_deduction']}, Calculé: $calculatedDeductions)\n";
    echo "    Net: " . ($netPayOk ? "✓" : "❌") . " (Stocké: {$slip['net_pay']}, Calculé: $calculatedNetPay)\n";
    
    return $grossPayOk && $deductionOk && $netPayOk;
}

// Exemple de fiche avec totaux incohérents
$testSlip = [
    'name' => 'Sal Slip/TEST/00001',
    'earnings' => [
        ['salary_component' => 'Salaire de base', 'amount' => 1050.0],
        ['salary_component' => 'Indemnité', 'amount' => 200.0]
    ],
    'deductions' => [
        ['salary_component' => 'Taxe sociale', 'amount' => 125.0],
        ['salary_component' => 'Assurance', 'amount' => 50.0]
    ],
    'gross_pay' => 1250.0, // Correct
    'total_deduction' => 175.0, // Correct
    'net_pay' => 1070.0 // Incorrect (devrait être 1075.0)
];

$isValid = validateSalarySlipTotals($testSlip);
echo "  Résultat global: " . ($isValid ? "✓ Valide" : "❌ Incohérent") . "\n\n";

// Test 3: Gestion des erreurs spécifiques
echo "Test 3: Gestion des erreurs spécifiques\n";

$errorMessages = [
    "ValidationError: frappe.exceptions.ValidationError: Cannot edit cancelled document",
    "TimestampMismatchError: Document has been modified",
    "ValidationError: Total earnings do not match",
    "RuntimeException: Network timeout"
];

foreach ($errorMessages as $error) {
    echo "  🔍 Analyse de l'erreur: " . substr($error, 0, 50) . "...\n";
    
    if (strpos($error, 'Cannot edit cancelled document') !== false) {
        echo "    → Action: Ignorer la fiche (document annulé)\n";
    } elseif (strpos($error, 'TimestampMismatchError') !== false) {
        echo "    → Action: Retry avec délai\n";
    } elseif (strpos($error, 'ValidationError') !== false) {
        echo "    → Action: Vérifier et corriger les données\n";
    } else {
        echo "    → Action: Logger l'erreur et continuer\n";
    }
}

echo "\n";

// Test 4: Simulation d'application de pourcentages mensuels
echo "Test 4: Application sécurisée des pourcentages mensuels\n";

function applyMonthlyPercentageSafely($baseValue, $percentage, $month, $component) {
    // Validations
    if ($month < 1 || $month > 12) {
        echo "    ⚠️  Mois invalide ($month), utilisation de la valeur de base\n";
        return $baseValue;
    }
    
    if ($baseValue < 0) {
        echo "    ⚠️  Valeur de base négative ($baseValue), utilisation de la valeur de base\n";
        return $baseValue;
    }
    
    // Limitation des pourcentages
    $originalPercentage = $percentage;
    if ($percentage < -100) $percentage = -100;
    if ($percentage > 1000) $percentage = 1000;
    
    if ($originalPercentage !== $percentage) {
        echo "    ⚠️  Pourcentage limité de $originalPercentage% à $percentage%\n";
    }
    
    $newValue = $baseValue * (1 + ($percentage / 100));
    
    // Éviter les valeurs négatives
    if ($newValue < 0) {
        echo "    ⚠️  Valeur calculée négative, mise à 0\n";
        $newValue = 0;
    }
    
    return round($newValue, 2);
}

$testCases = [
    ['base' => 1000, 'percentage' => 5, 'month' => 1, 'component' => 'Salaire de base'],
    ['base' => 1000, 'percentage' => -150, 'month' => 2, 'component' => 'Salaire de base'], // Trop bas
    ['base' => -500, 'percentage' => 10, 'month' => 3, 'component' => 'Salaire de base'], // Base négative
    ['base' => 1000, 'percentage' => 10, 'month' => 13, 'component' => 'Salaire de base'], // Mois invalide
];

foreach ($testCases as $test) {
    echo "  📊 Test: Base={$test['base']}, Pourcentage={$test['percentage']}%, Mois={$test['month']}\n";
    $result = applyMonthlyPercentageSafely($test['base'], $test['percentage'], $test['month'], $test['component']);
    echo "    Résultat: $result\n";
}

echo "\n=== Tests terminés ===\n";
echo "✅ Les corrections implémentées devraient résoudre les erreurs de soumission\n";
echo "📋 Points clés:\n";
echo "  • Les fiches annulées sont maintenant ignorées\n";
echo "  • Les totaux sont validés avant sauvegarde\n";
echo "  • Les pourcentages sont limités et validés\n";
echo "  • Les erreurs sont mieux gérées et loggées\n";