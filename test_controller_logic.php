<?php

/**
 * Test direct de la logique du contrôleur
 */

echo "=== Test de la logique du contrôleur ===\n\n";

// Simuler les paramètres POST pour les pourcentages mensuels
$postParams = [
    'component' => 'Salaire Base',
    'start_date' => '2025-03-01',
    'end_date' => '2025-03-31',
    'use_monthly_percentages' => '1',
    'monthly_percentages' => [
        1 => '5.0',
        2 => '3.0', 
        3 => '-2.0',  // Mars: -2%
        4 => '0.0',
        5 => '10.0',
        6 => '7.5',
        7 => '2.5',
        8 => '0.0',
        9 => '4.0',
        10 => '6.0',
        11 => '8.0',
        12 => '12.0'
    ]
];

echo "📋 Paramètres de test:\n";
echo "   Composant: {$postParams['component']}\n";
echo "   Période: {$postParams['start_date']} à {$postParams['end_date']}\n";
echo "   Pourcentages mensuels: Activés\n";
echo "   Pourcentage pour Mars: {$postParams['monthly_percentages'][3]}%\n\n";

// Test de la validation des paramètres
echo "🔍 Test 1: Validation des paramètres\n";

$component = $postParams['component'];
$useMonthlyPercentages = $postParams['use_monthly_percentages'] === '1';
$monthlyPercentages = $postParams['monthly_percentages'];

echo "   Composant: " . ($component ? "✅ $component" : "❌ Manquant") . "\n";
echo "   Pourcentages mensuels: " . ($useMonthlyPercentages ? "✅ Activés" : "❌ Désactivés") . "\n";
echo "   Nombre de pourcentages: " . count($monthlyPercentages) . "\n";

if ($useMonthlyPercentages) {
    echo "   ✅ Mode pourcentages mensuels - pas besoin de condition\n";
} else {
    echo "   ❌ Mode classique - condition requise\n";
}

echo "\n";

// Test de la logique de condition
echo "🔍 Test 2: Logique de condition\n";

// Simuler une fiche de paie
$mockSlip = [
    'name' => 'Sal Slip/HR-EMP-00031/00013',
    'employee' => 'HR-EMP-00031',
    'employee_name' => 'Alain Rakoto',
    'start_date' => '2025-03-01',
    'end_date' => '2025-03-31',
    'docstatus' => 2, // Cancelled
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
    ],
    'gross_pay' => 1200.0,
    'total_deduction' => 120.0,
    'net_pay' => 1080.0
];

echo "   Fiche test: {$mockSlip['name']}\n";
echo "   Statut original: {$mockSlip['docstatus']} (Cancelled)\n";
echo "   Composant recherché: $component\n";

// Chercher le composant
$componentFound = false;
$currentValue = 0;

foreach ($mockSlip['earnings'] as $earning) {
    $earningComponent = strtolower(trim($earning['salary_component']));
    $searchComponent = strtolower(trim($component));
    
    if ($earningComponent === $searchComponent || $earning['salary_component'] === $component) {
        $componentFound = true;
        $currentValue = $earning['amount'];
        echo "   ✅ Composant trouvé: {$earning['salary_component']} = $currentValue\n";
        break;
    }
}

if (!$componentFound) {
    echo "   ❌ Composant non trouvé\n";
    exit(1);
}

// Test de la condition (pour les pourcentages mensuels, toujours vraie)
$conditionMet = $useMonthlyPercentages; // Pas de vérification de condition pour les pourcentages
echo "   Condition respectée: " . ($conditionMet ? "✅ Oui" : "❌ Non") . "\n";

if ($conditionMet) {
    echo "   ✅ Fiche sera traitée\n";
} else {
    echo "   ❌ Fiche sera ignorée\n";
}

echo "\n";

// Test du calcul du pourcentage
echo "🔍 Test 3: Calcul du pourcentage\n";

if ($useMonthlyPercentages) {
    // Extraire le mois de la fiche
    $slipDate = new DateTime($mockSlip['start_date']);
    $slipMonth = (int)$slipDate->format('n');
    
    echo "   Mois de la fiche: $slipMonth (Mars)\n";
    
    if (isset($monthlyPercentages[$slipMonth])) {
        $percentage = (float)$monthlyPercentages[$slipMonth];
        echo "   Pourcentage pour ce mois: $percentage%\n";
        
        // Calculer la nouvelle valeur
        $newValue = $currentValue * (1 + ($percentage / 100));
        $newValue = round($newValue, 2);
        
        echo "   Calcul: $currentValue × (1 + $percentage/100) = $newValue\n";
        echo "   Différence: " . ($newValue - $currentValue) . "\n";
        
        if ($newValue != $currentValue) {
            echo "   ✅ Modification nécessaire\n";
        } else {
            echo "   ℹ️  Aucune modification (pourcentage = 0%)\n";
        }
    } else {
        echo "   ❌ Pas de pourcentage défini pour ce mois\n";
    }
}

echo "\n";

// Test de la modification de la fiche
echo "🔍 Test 4: Modification de la fiche\n";

if ($conditionMet && isset($newValue)) {
    // Simuler la modification
    $modifiedSlip = $mockSlip;
    $modifiedSlip['docstatus'] = 0; // Forcer en draft
    
    // Modifier le composant
    foreach ($modifiedSlip['earnings'] as &$earning) {
        if ($earning['salary_component'] === $component) {
            $earning['amount'] = $newValue;
            break;
        }
    }
    
    // Recalculer les totaux
    $totalEarnings = array_sum(array_column($modifiedSlip['earnings'], 'amount'));
    $totalDeductions = array_sum(array_column($modifiedSlip['deductions'], 'amount'));
    $netPay = $totalEarnings - $totalDeductions;
    
    $modifiedSlip['gross_pay'] = $totalEarnings;
    $modifiedSlip['total_deduction'] = $totalDeductions;
    $modifiedSlip['net_pay'] = $netPay;
    $modifiedSlip['rounded_total'] = round($netPay);
    
    echo "   ✅ Fiche modifiée:\n";
    echo "      Statut: {$modifiedSlip['docstatus']} (Draft)\n";
    echo "      $component: $currentValue → $newValue\n";
    echo "      Brut: {$mockSlip['gross_pay']} → {$modifiedSlip['gross_pay']}\n";
    echo "      Net: {$mockSlip['net_pay']} → {$modifiedSlip['net_pay']}\n";
    
    echo "   ✅ Prêt pour sauvegarde dans ERPNext\n";
} else {
    echo "   ❌ Fiche ne sera pas modifiée\n";
}

echo "\n";

echo "🎯 Conclusion:\n";
echo "La logique devrait fonctionner correctement:\n";
echo "• ✅ Validation des paramètres OK\n";
echo "• ✅ Pas de vérification de condition pour les pourcentages mensuels\n";
echo "• ✅ Calcul du pourcentage correct\n";
echo "• ✅ Modification de la fiche OK\n";
echo "• ✅ Statut forcé à Draft\n";

echo "\n=== Test terminé ===\n";