<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\SalaryGeneratorService;
use App\Service\ErpNextService;
use Symfony\Component\HttpClient\HttpClient;
use Psr\Log\NullLogger;

// Configuration pour ERPNext (à adapter selon votre environnement)
$erpNextUrl = 'http://localhost:8000'; // Remplacez par votre URL ERPNext
$apiKey = 'your-api-key';
$apiSecret = 'your-api-secret';

try {
    // Créer les services
    $httpClient = HttpClient::create();
    $logger = new NullLogger();
    
    $erpNextService = new ErpNextService($httpClient, $logger);
    $erpNextService->setCredentials($erpNextUrl, $apiKey, $apiSecret);
    
    $salaryGeneratorService = new SalaryGeneratorService($erpNextService, $logger);
    
    // Test de génération de salaire
    $startDate = new DateTime('2024-01-01');
    $endDate = new DateTime('2024-01-31');
    
    echo "🚀 Test de génération automatique de salaire\n";
    echo "Période: " . $startDate->format('Y-m-d') . " à " . $endDate->format('Y-m-d') . "\n\n";
    
    // Test 1: Génération normale
    echo "📋 Test 1: Génération normale (sans écrasement)\n";
    $result1 = $salaryGeneratorService->generate($startDate, $endDate, false, false);
    echo "Créées: {$result1['created']}, Ignorées: {$result1['skipped']}, Erreurs: " . count($result1['errors']) . "\n";
    if (!empty($result1['errors'])) {
        foreach ($result1['errors'] as $error) {
            echo "❌ $error\n";
        }
    }
    echo "\n";
    
    // Test 2: Génération avec salaire de base spécifique
    echo "📋 Test 2: Génération avec salaire de base spécifique (3000€)\n";
    $result2 = $salaryGeneratorService->generate($startDate, $endDate, true, false, 3000.0);
    echo "Créées: {$result2['created']}, Ignorées: {$result2['skipped']}, Erreurs: " . count($result2['errors']) . "\n";
    if (!empty($result2['errors'])) {
        foreach ($result2['errors'] as $error) {
            echo "❌ $error\n";
        }
    }
    echo "\n";
    
    // Test 3: Génération avec moyenne
    echo "📋 Test 3: Génération avec moyenne des salaires précédents\n";
    $result3 = $salaryGeneratorService->generate($startDate, $endDate, true, true);
    echo "Créées: {$result3['created']}, Ignorées: {$result3['skipped']}, Erreurs: " . count($result3['errors']) . "\n";
    if (!empty($result3['errors'])) {
        foreach ($result3['errors'] as $error) {
            echo "❌ $error\n";
        }
    }
    echo "\n";
    
    echo "✅ Tests terminés avec succès !\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors du test: " . $e->getMessage() . "\n";
    echo "💡 Assurez-vous que ERPNext est accessible et que les credentials sont corrects.\n";
}