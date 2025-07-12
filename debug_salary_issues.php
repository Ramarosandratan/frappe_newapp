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
    
    echo "🔍 Diagnostic des problèmes de génération de salaire\n\n";
    
    // 1. Vérifier les employés problématiques
    $problematicEmployees = ['HR-EMP-00029', 'HR-EMP-00030'];
    
    foreach ($problematicEmployees as $employeeId) {
        echo "👤 Analyse de l'employé: $employeeId\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        try {
            // Récupérer les détails de l'employé
            $employee = $erpNextService->getEmployee($employeeId);
            if ($employee) {
                echo "✅ Employé trouvé:\n";
                echo "   - Nom: " . ($employee['employee_name'] ?? 'N/A') . "\n";
                echo "   - Statut: " . ($employee['status'] ?? 'N/A') . "\n";
                echo "   - Société: " . ($employee['company'] ?? 'N/A') . "\n";
                echo "   - Champs disponibles: " . implode(', ', array_keys($employee)) . "\n";
                
                // Vérifier s'il y a un champ de salaire
                $salaryFields = ['salary_rate', 'basic_salary', 'base_salary', 'monthly_salary'];
                $foundSalary = false;
                foreach ($salaryFields as $field) {
                    if (isset($employee[$field]) && $employee[$field] > 0) {
                        echo "   - $field: " . $employee[$field] . "\n";
                        $foundSalary = true;
                    }
                }
                if (!$foundSalary) {
                    echo "   ⚠️ Aucun champ de salaire trouvé dans les détails de l'employé\n";
                }
            } else {
                echo "❌ Employé non trouvé\n";
            }
            
            // Vérifier l'assignation de structure salariale
            echo "\n📋 Assignation de structure salariale:\n";
            $assignment = $erpNextService->getEmployeeSalaryStructureAssignment($employeeId, '2024-01-01');
            if ($assignment) {
                echo "✅ Assignation trouvée:\n";
                echo "   - Structure: " . ($assignment['salary_structure'] ?? 'N/A') . "\n";
                echo "   - Base: " . ($assignment['base'] ?? 'N/A') . "\n";
                echo "   - Date début: " . ($assignment['from_date'] ?? 'N/A') . "\n";
                echo "   - Champs disponibles: " . implode(', ', array_keys($assignment)) . "\n";
            } else {
                echo "❌ Aucune assignation de structure salariale trouvée\n";
            }
            
            // Vérifier les fiches de paie existantes
            echo "\n💰 Fiches de paie existantes:\n";
            $existingSlips = $erpNextService->getSalarySlips([
                'employee' => $employeeId,
                'start_date' => '2024-01-01',
                'end_date' => '2024-01-31',
            ]);
            
            if (!empty($existingSlips)) {
                echo "✅ " . count($existingSlips) . " fiche(s) trouvée(s):\n";
                foreach ($existingSlips as $slip) {
                    echo "   - " . ($slip['name'] ?? 'N/A') . " (statut: " . ($slip['docstatus'] ?? 'N/A') . ")\n";
                    
                    // Essayer de récupérer les détails de la fiche
                    try {
                        $slipDetails = $erpNextService->getSalarySlipDetails($slip['name']);
                        if ($slipDetails) {
                            echo "     Base: " . ($slipDetails['base'] ?? 'N/A') . "\n";
                            echo "     Gains: " . count($slipDetails['earnings'] ?? []) . " composant(s)\n";
                            echo "     Déductions: " . count($slipDetails['deductions'] ?? []) . " composant(s)\n";
                        }
                    } catch (\Exception $e) {
                        echo "     ❌ Erreur récupération détails: " . $e->getMessage() . "\n";
                    }
                }
            } else {
                echo "ℹ️ Aucune fiche de paie existante pour janvier 2024\n";
            }
            
            // Tester la suppression d'une fiche si elle existe
            if (!empty($existingSlips)) {
                $firstSlip = $existingSlips[0];
                echo "\n🗑️ Test de suppression de la fiche: " . $firstSlip['name'] . "\n";
                
                try {
                    $deleteResult = $erpNextService->deleteSalarySlip($firstSlip['name']);
                    if ($deleteResult) {
                        echo "✅ Suppression réussie\n";
                    } else {
                        echo "❌ Suppression échouée\n";
                    }
                } catch (\Exception $e) {
                    echo "❌ Erreur lors de la suppression: " . $e->getMessage() . "\n";
                }
            }
            
        } catch (\Exception $e) {
            echo "❌ Erreur lors de l'analyse: " . $e->getMessage() . "\n";
        }
        
        echo "\n" . str_repeat("-", 60) . "\n\n";
    }
    
    // 2. Vérifier les structures salariales disponibles
    echo "🏗️ Structures salariales disponibles:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    try {
        $structures = $erpNextService->getSalaryStructures();
        if (!empty($structures)) {
            echo "✅ " . count($structures) . " structure(s) trouvée(s):\n";
            foreach ($structures as $structure) {
                echo "   - " . ($structure['name'] ?? 'N/A') . "\n";
            }
        } else {
            echo "❌ Aucune structure salariale trouvée\n";
        }
    } catch (\Exception $e) {
        echo "❌ Erreur récupération structures: " . $e->getMessage() . "\n";
    }
    
    echo "\n🎯 Recommandations:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    echo "1. Vérifiez que les employés ont une assignation de structure salariale active\n";
    echo "2. Vérifiez que les structures salariales ont un montant de base défini\n";
    echo "3. Si la suppression échoue, vérifiez les permissions dans ERPNext\n";
    echo "4. Consultez les logs détaillés pour plus d'informations\n";
    
} catch (Exception $e) {
    echo "❌ Erreur générale: " . $e->getMessage() . "\n";
    echo "📝 Trace: " . $e->getTraceAsString() . "\n";
}