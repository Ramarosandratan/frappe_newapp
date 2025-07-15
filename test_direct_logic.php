<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

// Créer le kernel Symfony
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== Test direct de la logique ===\n\n";

try {
    // Récupérer les services
    $erpNextService = $container->get('App\Service\ErpNextService');
    $monthlyPercentageService = $container->get('App\Service\MonthlyPercentageService');
    
    echo "✅ Services chargés avec succès\n\n";
    
    // Test 1: Récupérer les fiches de paie pour Mars 2025
    echo "📋 Test 1: Récupération des fiches de paie\n";
    $slips = $erpNextService->getSalarySlipsByPeriod('2025-03-01', '2025-03-31');
    
    echo "   Fiches trouvées: " . count($slips) . "\n";
    
    if (empty($slips)) {
        echo "   ❌ Aucune fiche trouvée pour la période\n";
        exit(1);
    }
    
    // Afficher les détails des fiches
    foreach ($slips as $index => $slip) {
        echo "   Fiche " . ($index + 1) . ": {$slip['name']} - {$slip['employee_name']}\n";
        echo "      Statut: {$slip['docstatus']} - Brut: {$slip['gross_pay']}\n";
    }
    
    echo "\n";
    
    // Test 2: Récupérer les détails complets d'une fiche
    echo "📄 Test 2: Récupération des détails complets\n";
    $firstSlip = $slips[0];
    $slipDetails = $erpNextService->getSalarySlipDetails($firstSlip['name']);
    
    if ($slipDetails) {
        echo "   ✅ Détails récupérés pour: {$slipDetails['name']}\n";
        echo "   Statut: {$slipDetails['docstatus']}\n";
        echo "   Composants earnings: " . count($slipDetails['earnings'] ?? []) . "\n";
        echo "   Composants deductions: " . count($slipDetails['deductions'] ?? []) . "\n";
        
        // Chercher le composant "Salaire Base"
        $salaireBaseFound = false;
        $salaireBaseAmount = 0;
        
        foreach ($slipDetails['earnings'] ?? [] as $earning) {
            echo "      Earning: {$earning['salary_component']} = {$earning['amount']}\n";
            if (stripos($earning['salary_component'], 'salaire') !== false && 
                stripos($earning['salary_component'], 'base') !== false) {
                $salaireBaseFound = true;
                $salaireBaseAmount = $earning['amount'];
            }
        }
        
        if ($salaireBaseFound) {
            echo "   ✅ Composant 'Salaire Base' trouvé: $salaireBaseAmount\n";
        } else {
            echo "   ❌ Composant 'Salaire Base' non trouvé\n";
        }
    } else {
        echo "   ❌ Impossible de récupérer les détails\n";
        exit(1);
    }
    
    echo "\n";
    
    // Test 3: Sauvegarder des pourcentages mensuels
    echo "💾 Test 3: Sauvegarde des pourcentages mensuels\n";
    $monthlyPercentages = [
        1 => 5.0,   // Janvier: +5%
        2 => 3.0,   // Février: +3%
        3 => -2.0,  // Mars: -2%
        4 => 0.0,   // Avril: 0%
        5 => 10.0,  // Mai: +10%
        6 => 7.5,   // Juin: +7.5%
        7 => 2.5,   // Juillet: +2.5%
        8 => 0.0,   // Août: 0%
        9 => 4.0,   // Septembre: +4%
        10 => 6.0,  // Octobre: +6%
        11 => 8.0,  // Novembre: +8%
        12 => 12.0  // Décembre: +12%
    ];
    
    $monthlyPercentageService->saveMonthlyPercentages('Salaire Base', $monthlyPercentages);
    echo "   ✅ Pourcentages sauvegardés\n";
    
    // Test 4: Appliquer le pourcentage pour Mars
    echo "   Test du calcul pour Mars (-2%):\n";
    $originalAmount = $salaireBaseAmount;
    $newAmount = $monthlyPercentageService->applyMonthlyPercentage($originalAmount, 3, 'Salaire Base');
    echo "      Original: $originalAmount\n";
    echo "      Nouveau: $newAmount\n";
    echo "      Différence: " . ($newAmount - $originalAmount) . "\n";
    
    echo "\n";
    
    // Test 5: Modifier la fiche de paie
    echo "🔧 Test 5: Modification de la fiche de paie\n";
    
    // Préparer les données modifiées
    $modifiedSlip = $slipDetails;
    $modifiedSlip['docstatus'] = 0; // Forcer en draft
    
    // Modifier le composant Salaire Base
    foreach ($modifiedSlip['earnings'] as &$earning) {
        if (stripos($earning['salary_component'], 'salaire') !== false && 
            stripos($earning['salary_component'], 'base') !== false) {
            $earning['amount'] = $newAmount;
            echo "   Modification: {$earning['salary_component']} = $originalAmount → $newAmount\n";
            break;
        }
    }
    
    // Recalculer les totaux
    $totalEarnings = array_sum(array_column($modifiedSlip['earnings'], 'amount'));
    $totalDeductions = array_sum(array_column($modifiedSlip['deductions'] ?? [], 'amount'));
    $netPay = $totalEarnings - $totalDeductions;
    
    $modifiedSlip['gross_pay'] = $totalEarnings;
    $modifiedSlip['total_deduction'] = $totalDeductions;
    $modifiedSlip['net_pay'] = $netPay;
    $modifiedSlip['rounded_total'] = round($netPay);
    
    echo "   Nouveaux totaux:\n";
    echo "      Brut: $totalEarnings\n";
    echo "      Déductions: $totalDeductions\n";
    echo "      Net: $netPay\n";
    
    // Sauvegarder la fiche modifiée
    echo "   Sauvegarde de la fiche modifiée...\n";
    $result = $erpNextService->updateSalarySlip($modifiedSlip);
    
    if ($result) {
        echo "   ✅ Fiche sauvegardée avec succès!\n";
        echo "   Statut final: 0 (Draft)\n";
    } else {
        echo "   ❌ Échec de la sauvegarde\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test terminé ===\n";