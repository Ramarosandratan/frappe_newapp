<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use App\Service\ErpNextService;
use Symfony\Component\HttpClient\HttpClient;
use Psr\Log\NullLogger;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

// Configuration ERPNext
$apiBase = $_ENV['API_BASE'] ?? 'http://erpnext.localhost:8000';
$apiKey = $_ENV['API_KEY'] ?? '';
$apiSecret = $_ENV['API_SECRET'] ?? '';

if (empty($apiKey) || empty($apiSecret)) {
    echo "❌ Erreur: Variables d'environnement ERPNext manquantes\n";
    exit(1);
}

// Créer le service ERPNext
$httpClient = HttpClient::create();
$logger = new NullLogger();
$erpNextService = new ErpNextService($httpClient, $logger, $apiBase, $apiKey, $apiSecret);

echo "🧪 Test de la mise à jour des montants de salaire\n";
echo "================================================\n\n";

try {
    // Test 1: Vérifier la connexion
    echo "1. Test de connexion à ERPNext...\n";
    $user = $erpNextService->findUserByEmail('ramarosandratana@hotmail.com');
    if ($user) {
        echo "   ✅ Connexion réussie\n\n";
    } else {
        echo "   ❌ Échec de connexion\n";
        exit(1);
    }
    
    // Test 2: Créer une fiche de paie de test avec montant de base
    echo "2. Test de création de fiche de paie avec montant...\n";
    
    // Données de test - utilisons décembre 2025 (période future mais dans l'année fiscale)
    $testData = [
        'employee' => 'HR-EMP-00027', // Remplacer par un ID d'employé existant
        'start_date' => '2025-12-01',
        'end_date' => '2025-12-31',
        'posting_date' => '2025-12-31',
        'salary_structure' => 'gasy1',
        'base' => 1500000.0
    ];
    
    echo "   Données de test:\n";
    echo "   - Employé: {$testData['employee']}\n";
    echo "   - Période: {$testData['start_date']} à {$testData['end_date']}\n";
    echo "   - Structure: {$testData['salary_structure']}\n";
    echo "   - Montant de base: " . number_format($testData['base'], 0, ',', ' ') . " Ar\n\n";
    
    // Vérifier si l'employé existe
    $employee = $erpNextService->getEmployee($testData['employee']);
    if (!$employee) {
        echo "   ❌ Employé {$testData['employee']} introuvable\n";
        echo "   💡 Veuillez vérifier l'ID de l'employé dans ERPNext\n";
        exit(1);
    }
    
    // Vérifier si la structure salariale existe
    $structure = $erpNextService->getSalaryStructure($testData['salary_structure']);
    if (!$structure) {
        echo "   ❌ Structure salariale {$testData['salary_structure']} introuvable\n";
        echo "   💡 Veuillez créer la structure salariale d'abord\n";
        exit(1);
    }
    
    // Assigner la structure salariale à l'employé
    echo "   Attribution de la structure salariale...\n";
    try {
        $assignmentDate = '2024-12-01'; // Date antérieure à la période de paie
        $erpNextService->assignSalaryStructure(
            $testData['employee'],
            $testData['salary_structure'],
            $assignmentDate
        );
        echo "   ✅ Structure salariale assignée\n";
        
        // Attendre un peu pour que l'assignation soit enregistrée
        sleep(2);
        
        // Mettre à jour le montant de base dans l'assignation
        echo "   Mise à jour du montant de base dans l'assignation...\n";
        $erpNextService->updateSalaryStructureAssignmentBase(
            $testData['employee'],
            $testData['salary_structure'],
            $assignmentDate,
            $testData['base']
        );
        echo "   ✅ Montant de base mis à jour\n";
        
    } catch (\Throwable $e) {
        if (str_contains($e->getMessage(), 'DuplicateEntryError') || str_contains($e->getMessage(), 'already exists')) {
            echo "   ℹ️  Structure déjà assignée\n";
            
            // Mettre à jour le montant de base quand même
            try {
                $erpNextService->updateSalaryStructureAssignmentBase(
                    $testData['employee'],
                    $testData['salary_structure'],
                    $assignmentDate,
                    $testData['base']
                );
                echo "   ✅ Montant de base mis à jour\n";
            } catch (\Throwable $e2) {
                echo "   ⚠️  Erreur lors de la mise à jour du montant: " . $e2->getMessage() . "\n";
            }
        } else {
            echo "   ⚠️  Erreur lors de l'assignation: " . $e->getMessage() . "\n";
        }
    }
    
    // Créer la fiche de paie
    echo "   Création de la fiche de paie...\n";
    $result = $erpNextService->addSalarySlip($testData);
    
    if (isset($result['name'])) {
        echo "   ✅ Fiche de paie créée: {$result['name']}\n";
        
        // Vérifier les montants
        echo "\n3. Vérification des montants...\n";
        $salarySlip = $erpNextService->getResource('Salary Slip', $result['name']);
        
        if ($salarySlip) {
            echo "   Montants calculés:\n";
            echo "   - Total gains: " . number_format($salarySlip['total_earning'] ?? 0, 0, ',', ' ') . " Ar\n";
            echo "   - Total déductions: " . number_format($salarySlip['total_deduction'] ?? 0, 0, ',', ' ') . " Ar\n";
            echo "   - Salaire net: " . number_format($salarySlip['net_pay'] ?? 0, 0, ',', ' ') . " Ar\n\n";
            
            // Vérifier les composants
            if (isset($salarySlip['earnings']) && is_array($salarySlip['earnings'])) {
                echo "   Détail des gains:\n";
                foreach ($salarySlip['earnings'] as $earning) {
                    $component = $earning['salary_component'] ?? 'Inconnu';
                    $amount = $earning['amount'] ?? 0;
                    echo "   - $component: " . number_format($amount, 0, ',', ' ') . " Ar\n";
                }
            }
            
            if (isset($salarySlip['deductions']) && is_array($salarySlip['deductions'])) {
                echo "\n   Détail des déductions:\n";
                foreach ($salarySlip['deductions'] as $deduction) {
                    $component = $deduction['salary_component'] ?? 'Inconnu';
                    $amount = $deduction['amount'] ?? 0;
                    echo "   - $component: " . number_format($amount, 0, ',', ' ') . " Ar\n";
                }
            }
            
            // Vérifier si les montants sont corrects
            $expectedSalaryBase = 1500000;
            $expectedIndemnity = $expectedSalaryBase * 0.3;
            $expectedTax = ($expectedSalaryBase + $expectedIndemnity) * 0.2;
            $expectedNet = ($expectedSalaryBase + $expectedIndemnity) - $expectedTax;
            
            echo "\n   Montants attendus:\n";
            echo "   - Salaire de base: " . number_format($expectedSalaryBase, 0, ',', ' ') . " Ar\n";
            echo "   - Indemnité (30%): " . number_format($expectedIndemnity, 0, ',', ' ') . " Ar\n";
            echo "   - Taxe sociale (20%): " . number_format($expectedTax, 0, ',', ' ') . " Ar\n";
            echo "   - Salaire net: " . number_format($expectedNet, 0, ',', ' ') . " Ar\n\n";
            
            $actualNet = $salarySlip['net_pay'] ?? 0;
            if (abs($actualNet - $expectedNet) < 1) {
                echo "   ✅ Les montants sont corrects !\n";
            } else {
                echo "   ⚠️  Différence détectée dans les montants\n";
                echo "   Attendu: " . number_format($expectedNet, 0, ',', ' ') . " Ar\n";
                echo "   Obtenu: " . number_format($actualNet, 0, ',', ' ') . " Ar\n";
            }
        } else {
            echo "   ❌ Impossible de récupérer la fiche de paie créée\n";
        }
    } else {
        echo "   ❌ Échec de création de la fiche de paie\n";
        print_r($result);
    }
    
} catch (\Throwable $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n🏁 Test terminé\n";