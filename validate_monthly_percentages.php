<?php

/**
 * Script de validation simple pour les pourcentages mensuels
 * Teste la logique de calcul sans dépendances Symfony
 */

echo "=== Validation des pourcentages mensuels ===\n\n";

// Simulation de la logique d'application des pourcentages
function applyMonthlyPercentage(float $baseValue, float $percentage): float
{
    // Validation des paramètres
    if ($baseValue < 0) {
        echo "⚠️  Valeur de base négative détectée: $baseValue\n";
        return $baseValue;
    }
    
    // Limitation des pourcentages extrêmes
    if ($percentage < -100) {
        echo "⚠️  Pourcentage trop bas ($percentage%), limité à -100%\n";
        $percentage = -100;
    }
    
    if ($percentage > 1000) {
        echo "⚠️  Pourcentage trop haut ($percentage%), limité à 1000%\n";
        $percentage = 1000;
    }
    
    $newValue = $baseValue * (1 + ($percentage / 100));
    
    // S'assurer que la nouvelle valeur n'est pas négative
    if ($newValue < 0) {
        echo "⚠️  Valeur calculée négative ($newValue), mise à 0\n";
        $newValue = 0;
    }
    
    return round($newValue, 2);
}

// Test 1: Calculs normaux
echo "Test 1: Calculs normaux\n";
$testCases = [
    ['base' => 1000, 'percentage' => 5.0, 'expected' => 1050.0],
    ['base' => 1000, 'percentage' => -10.0, 'expected' => 900.0],
    ['base' => 1000, 'percentage' => 0.0, 'expected' => 1000.0],
    ['base' => 500, 'percentage' => 15.5, 'expected' => 577.5],
];

foreach ($testCases as $test) {
    $result = applyMonthlyPercentage($test['base'], $test['percentage']);
    $status = abs($result - $test['expected']) < 0.01 ? '✓' : '✗';
    echo sprintf("  %s Base: %.2f, Pourcentage: %.1f%% → Résultat: %.2f (Attendu: %.2f)\n", 
        $status, $test['base'], $test['percentage'], $result, $test['expected']);
}

// Test 2: Cas limites
echo "\nTest 2: Cas limites\n";
$limitCases = [
    ['base' => 1000, 'percentage' => -150.0, 'description' => 'Pourcentage trop bas'],
    ['base' => 1000, 'percentage' => 2000.0, 'description' => 'Pourcentage trop haut'],
    ['base' => -100, 'percentage' => 10.0, 'description' => 'Valeur de base négative'],
    ['base' => 100, 'percentage' => -100.0, 'description' => 'Réduction de 100%'],
];

foreach ($limitCases as $test) {
    echo "  Test: {$test['description']}\n";
    $result = applyMonthlyPercentage($test['base'], $test['percentage']);
    echo "    Résultat: $result\n";
}

// Test 3: Validation de la cohérence des totaux
echo "\nTest 3: Validation de la cohérence des totaux\n";

function validateSalarySlipTotals(array $earnings, array $deductions): array
{
    $calculatedEarnings = array_sum(array_column($earnings, 'amount'));
    $calculatedDeductions = array_sum(array_column($deductions, 'amount'));
    $calculatedNetPay = $calculatedEarnings - $calculatedDeductions;
    
    return [
        'gross_pay' => $calculatedEarnings,
        'total_deduction' => $calculatedDeductions,
        'net_pay' => $calculatedNetPay
    ];
}

// Exemple de fiche de paie
$earnings = [
    ['salary_component' => 'Salaire de base', 'amount' => 1050.0], // Après +5%
    ['salary_component' => 'Indemnité transport', 'amount' => 200.0],
];

$deductions = [
    ['salary_component' => 'Taxe sociale', 'amount' => 125.0], // 10% du salaire de base
    ['salary_component' => 'Assurance', 'amount' => 50.0],
];

$totals = validateSalarySlipTotals($earnings, $deductions);

echo "  Gains totaux: {$totals['gross_pay']}\n";
echo "  Déductions totales: {$totals['total_deduction']}\n";
echo "  Salaire net: {$totals['net_pay']}\n";

// Vérification de cohérence
$expectedGross = 1250.0;
$expectedDeductions = 175.0;
$expectedNet = 1075.0;

$grossOk = abs($totals['gross_pay'] - $expectedGross) < 0.01;
$deductionsOk = abs($totals['total_deduction'] - $expectedDeductions) < 0.01;
$netOk = abs($totals['net_pay'] - $expectedNet) < 0.01;

echo "  Validation:\n";
echo "    Gains: " . ($grossOk ? '✓' : '✗') . "\n";
echo "    Déductions: " . ($deductionsOk ? '✓' : '✗') . "\n";
echo "    Net: " . ($netOk ? '✓' : '✗') . "\n";

// Test 4: Simulation d'application mensuelle
echo "\nTest 4: Simulation d'application mensuelle\n";

$monthlyPercentages = [
    1 => 5.0,    // Janvier: +5%
    2 => 3.0,    // Février: +3%
    3 => -2.0,   // Mars: -2%
    4 => 0.0,    // Avril: 0%
    5 => 10.0,   // Mai: +10%
    6 => 7.5,    // Juin: +7.5%
];

$baseSalary = 1000.0;
$monthNames = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

echo "  Salaire de base: $baseSalary\n";
echo "  Application mensuelle:\n";

for ($month = 1; $month <= 12; $month++) {
    $percentage = $monthlyPercentages[$month] ?? 0.0;
    $newSalary = applyMonthlyPercentage($baseSalary, $percentage);
    $change = $newSalary - $baseSalary;
    
    echo sprintf("    %s: %.2f (%.1f%%, %+.2f)\n", 
        $monthNames[$month], $newSalary, $percentage, $change);
}

echo "\n=== Tests terminés ===\n";
echo "✓ = Test réussi\n";
echo "✗ = Test échoué\n";
echo "⚠️  = Avertissement/Correction automatique\n";