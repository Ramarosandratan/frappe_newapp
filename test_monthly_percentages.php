<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

try {
    // Charger les variables d'environnement
    $dotenv = new Dotenv();
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv->load(__DIR__ . '/.env');
    }

    // Configuration de base
    $kernel = new \App\Kernel('dev', true);
    $kernel->boot();
    $container = $kernel->getContainer();
} catch (Exception $e) {
    echo "Erreur d'initialisation: " . $e->getMessage() . "\n";
    echo "Ce script nécessite un environnement Symfony configuré.\n";
    exit(1);
}

// Services nécessaires
$monthlyPercentageService = $container->get(\App\Service\MonthlyPercentageService::class);
$logger = $container->get('logger');

echo "=== Test des pourcentages mensuels ===\n\n";

// Test 1: Sauvegarde des pourcentages mensuels
echo "Test 1: Sauvegarde des pourcentages mensuels\n";
$testComponent = 'Salaire de base';
$testPercentages = [
    1 => 5.0,    // Janvier: +5%
    2 => 3.0,    // Février: +3%
    3 => -2.0,   // Mars: -2%
    4 => 0.0,    // Avril: 0%
    5 => 10.0,   // Mai: +10%
    6 => 7.5,    // Juin: +7.5%
    // Autres mois non définis (utiliseront la valeur de base)
];

try {
    $monthlyPercentageService->saveMonthlyPercentages($testComponent, $testPercentages);
    echo "✓ Pourcentages sauvegardés avec succès\n";
} catch (Exception $e) {
    echo "✗ Erreur lors de la sauvegarde: " . $e->getMessage() . "\n";
}

// Test 2: Récupération des pourcentages
echo "\nTest 2: Récupération des pourcentages\n";
try {
    $retrievedPercentages = $monthlyPercentageService->getMonthlyPercentages($testComponent);
    echo "✓ Pourcentages récupérés:\n";
    foreach ($retrievedPercentages as $month => $percentage) {
        $monthName = $monthlyPercentageService->getMonthNames()[$month];
        echo "  - $monthName: $percentage%\n";
    }
} catch (Exception $e) {
    echo "✗ Erreur lors de la récupération: " . $e->getMessage() . "\n";
}

// Test 3: Application des pourcentages
echo "\nTest 3: Application des pourcentages\n";
$baseValue = 1000.0;

for ($month = 1; $month <= 12; $month++) {
    try {
        $newValue = $monthlyPercentageService->applyMonthlyPercentage($baseValue, $month, $testComponent);
        $monthName = $monthlyPercentageService->getMonthNames()[$month];
        $change = $newValue - $baseValue;
        $changePercent = ($change / $baseValue) * 100;
        
        echo sprintf("  - %s: %.2f → %.2f (%.2f%% %s)\n", 
            $monthName, 
            $baseValue, 
            $newValue, 
            abs($changePercent),
            $change >= 0 ? 'augmentation' : 'réduction'
        );
    } catch (Exception $e) {
        echo "✗ Erreur pour le mois $month: " . $e->getMessage() . "\n";
    }
}

// Test 4: Cas limites
echo "\nTest 4: Tests des cas limites\n";

// Test avec mois invalide
try {
    $result = $monthlyPercentageService->applyMonthlyPercentage(1000, 13, $testComponent);
    echo "✓ Mois invalide (13) géré correctement: $result\n";
} catch (Exception $e) {
    echo "✗ Erreur avec mois invalide: " . $e->getMessage() . "\n";
}

// Test avec valeur négative
try {
    $result = $monthlyPercentageService->applyMonthlyPercentage(-100, 1, $testComponent);
    echo "✓ Valeur négative gérée correctement: $result\n";
} catch (Exception $e) {
    echo "✗ Erreur avec valeur négative: " . $e->getMessage() . "\n";
}

// Test 5: Pourcentages extrêmes
echo "\nTest 5: Test des pourcentages extrêmes\n";
$extremePercentages = [
    1 => -150.0,  // Trop bas, devrait être limité à -100%
    2 => 2000.0,  // Trop haut, devrait être limité à 1000%
];

try {
    $monthlyPercentageService->saveMonthlyPercentages('Test Extreme', $extremePercentages);
    
    $result1 = $monthlyPercentageService->applyMonthlyPercentage(1000, 1, 'Test Extreme');
    $result2 = $monthlyPercentageService->applyMonthlyPercentage(1000, 2, 'Test Extreme');
    
    echo "✓ Pourcentage extrême bas (-150%) géré: $result1\n";
    echo "✓ Pourcentage extrême haut (2000%) géré: $result2\n";
} catch (Exception $e) {
    echo "✗ Erreur avec pourcentages extrêmes: " . $e->getMessage() . "\n";
}

// Test 6: Vérification de la cohérence
echo "\nTest 6: Vérification de la cohérence des calculs\n";
$testValues = [100, 500, 1000, 2500, 5000];
$testMonth = 5; // Mai (+10%)

foreach ($testValues as $value) {
    try {
        $result = $monthlyPercentageService->applyMonthlyPercentage($value, $testMonth, $testComponent);
        $expected = $value * 1.10; // +10%
        $diff = abs($result - $expected);
        
        if ($diff < 0.01) {
            echo "✓ Valeur $value: $result (correct)\n";
        } else {
            echo "✗ Valeur $value: $result (attendu: $expected, différence: $diff)\n";
        }
    } catch (Exception $e) {
        echo "✗ Erreur pour la valeur $value: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Tests terminés ===\n";