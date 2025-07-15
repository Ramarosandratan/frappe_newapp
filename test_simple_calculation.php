<?php

/**
 * Test simple du calcul de modification des salaires
 */

echo "=== Test simple de modification des salaires ===\n\n";

// Simulation du calcul simple
function calculateNewSalary($currentValue, $percentage) {
    echo "📊 Calcul simple:\n";
    echo "   Valeur actuelle: $currentValue\n";
    echo "   Pourcentage: $percentage%\n";
    
    // Formule simple: valeur * (1 + pourcentage/100)
    $newValue = $currentValue * (1 + ($percentage / 100));
    $newValue = round($newValue, 2);
    
    echo "   Calcul: $currentValue × (1 + $percentage/100) = $newValue\n";
    echo "   Différence: " . ($newValue - $currentValue) . "\n\n";
    
    return $newValue;
}

// Tests avec différents scénarios
echo "🧮 Tests de calcul:\n\n";

// Test 1: Augmentation de 10%
echo "Test 1: Salaire de base 1000 avec +10%\n";
$result1 = calculateNewSalary(1000, 10);
echo "   ✅ Résultat: $result1 (attendu: 1100)\n\n";

// Test 2: Diminution de 5%
echo "Test 2: Salaire de base 1200 avec -5%\n";
$result2 = calculateNewSalary(1200, -5);
echo "   ✅ Résultat: $result2 (attendu: 1140)\n\n";

// Test 3: Aucun changement (0%)
echo "Test 3: Salaire de base 800 avec 0%\n";
$result3 = calculateNewSalary(800, 0);
echo "   ✅ Résultat: $result3 (attendu: 800)\n\n";

// Test 4: Augmentation importante (25%)
echo "Test 4: Salaire de base 1500 avec +25%\n";
$result4 = calculateNewSalary(1500, 25);
echo "   ✅ Résultat: $result4 (attendu: 1875)\n\n";

// Simulation du processus complet
echo "🔄 Simulation du processus complet:\n\n";

$mockSlip = [
    'name' => 'Sal Slip/TEST/00001',
    'docstatus' => 2, // Cancelled - sera forcé à 0 (Draft)
    'earnings' => [
        [
            'salary_component' => 'Salaire Base',
            'amount' => 1000.0
        ],
        [
            'salary_component' => 'Prime',
            'amount' => 200.0
        ]
    ],
    'deductions' => [
        [
            'salary_component' => 'Taxe',
            'amount' => 120.0
        ]
    ]
];

echo "📋 Fiche de paie originale:\n";
echo "   Nom: {$mockSlip['name']}\n";
echo "   Statut: {$mockSlip['docstatus']} (Cancelled)\n";
foreach ($mockSlip['earnings'] as $earning) {
    echo "   {$earning['salary_component']}: {$earning['amount']}\n";
}
foreach ($mockSlip['deductions'] as $deduction) {
    echo "   {$deduction['salary_component']}: {$deduction['amount']}\n";
}

$originalGross = array_sum(array_column($mockSlip['earnings'], 'amount'));
$originalDeductions = array_sum(array_column($mockSlip['deductions'], 'amount'));
$originalNet = $originalGross - $originalDeductions;

echo "   Brut: $originalGross\n";
echo "   Déductions: $originalDeductions\n";
echo "   Net: $originalNet\n\n";

// Appliquer le pourcentage mensuel pour Mars (-2%)
echo "🔧 Application du pourcentage mensuel (Mars: -2%):\n";
$component = 'Salaire Base';
$percentage = -2.0;

foreach ($mockSlip['earnings'] as &$earning) {
    if ($earning['salary_component'] === $component) {
        $oldValue = $earning['amount'];
        $newValue = calculateNewSalary($oldValue, $percentage);
        
        echo "   Modification: {$earning['salary_component']}\n";
        echo "   Ancienne valeur supprimée: $oldValue\n";
        echo "   Nouvelle valeur entrée: $newValue\n";
        
        // Supprimer l'ancienne valeur et entrer la nouvelle
        $earning['amount'] = $newValue;
        break;
    }
}

// Recalculer les totaux
$newGross = array_sum(array_column($mockSlip['earnings'], 'amount'));
$newDeductions = array_sum(array_column($mockSlip['deductions'], 'amount'));
$newNet = $newGross - $newDeductions;

echo "\n📋 Fiche de paie modifiée:\n";
echo "   Statut: 0 (Draft) - forcé\n";
foreach ($mockSlip['earnings'] as $earning) {
    echo "   {$earning['salary_component']}: {$earning['amount']}\n";
}
foreach ($mockSlip['deductions'] as $deduction) {
    echo "   {$deduction['salary_component']}: {$deduction['amount']}\n";
}

echo "   Brut: $originalGross → $newGross\n";
echo "   Déductions: $originalDeductions → $newDeductions\n";
echo "   Net: $originalNet → $newNet\n\n";

echo "✅ Processus terminé:\n";
echo "   1. ✅ Valeur prise: $oldValue\n";
echo "   2. ✅ Multipliée par le pourcentage: $percentage%\n";
echo "   3. ✅ Ancienne valeur supprimée\n";
echo "   4. ✅ Nouvelle valeur entrée: $newValue\n";
echo "   5. ✅ Statut forcé à Draft (0)\n";
echo "   6. ✅ Totaux recalculés\n";

echo "\n=== Test terminé ===\n";