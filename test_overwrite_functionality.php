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
    
    // Test de la fonctionnalité d'écrasement
    $startDate = new DateTime('2024-01-01');
    $endDate = new DateTime('2024-01-31');
    
    echo "🧪 Test de la fonctionnalité d'écrasement\n";
    echo "Période: " . $startDate->format('Y-m-d') . " à " . $endDate->format('Y-m-d') . "\n\n";
    
    // Étape 1: Créer des fiches de paie normalement
    echo "📋 Étape 1: Création initiale des fiches de paie\n";
    $result1 = $salaryGeneratorService->generate($startDate, $endDate, false, false);
    echo "Créées: {$result1['created']}, Ignorées: {$result1['skipped']}, Supprimées: {$result1['deleted']}\n";
    if (!empty($result1['errors'])) {
        foreach ($result1['errors'] as $error) {
            echo "❌ $error\n";
        }
    }
    echo "\n";
    
    // Étape 2: Essayer de recréer sans écrasement (devrait ignorer)
    echo "📋 Étape 2: Tentative de recréation sans écrasement (devrait ignorer)\n";
    $result2 = $salaryGeneratorService->generate($startDate, $endDate, false, false);
    echo "Créées: {$result2['created']}, Ignorées: {$result2['skipped']}, Supprimées: {$result2['deleted']}\n";
    if (!empty($result2['errors'])) {
        foreach ($result2['errors'] as $error) {
            echo "❌ $error\n";
        }
    }
    echo "\n";
    
    // Étape 3: Recréer avec écrasement (devrait supprimer puis recréer)
    echo "📋 Étape 3: Recréation avec écrasement (devrait supprimer puis recréer)\n";
    $result3 = $salaryGeneratorService->generate($startDate, $endDate, true, false);
    echo "Créées: {$result3['created']}, Ignorées: {$result3['skipped']}, Supprimées: {$result3['deleted']}\n";
    if (!empty($result3['errors'])) {
        foreach ($result3['errors'] as $error) {
            echo "❌ $error\n";
        }
    }
    echo "\n";
    
    // Étape 4: Test avec salaire spécifique et écrasement
    echo "📋 Étape 4: Recréation avec salaire spécifique (3500€) et écrasement\n";
    $result4 = $salaryGeneratorService->generate($startDate, $endDate, true, false, 3500.0);
    echo "Créées: {$result4['created']}, Ignorées: {$result4['skipped']}, Supprimées: {$result4['deleted']}\n";
    if (!empty($result4['errors'])) {
        foreach ($result4['errors'] as $error) {
            echo "❌ $error\n";
        }
    }
    echo "\n";
    
    // Résumé
    echo "✅ Test de la fonctionnalité d'écrasement terminé !\n";
    echo "\n📊 Résumé des résultats :\n";
    echo "- Étape 1 (création initiale): {$result1['created']} créées, {$result1['skipped']} ignorées, {$result1['deleted']} supprimées\n";
    echo "- Étape 2 (sans écrasement): {$result2['created']} créées, {$result2['skipped']} ignorées, {$result2['deleted']} supprimées\n";
    echo "- Étape 3 (avec écrasement): {$result3['created']} créées, {$result3['skipped']} ignorées, {$result3['deleted']} supprimées\n";
    echo "- Étape 4 (salaire spécifique + écrasement): {$result4['created']} créées, {$result4['skipped']} ignorées, {$result4['deleted']} supprimées\n";
    
    // Validation des résultats attendus
    $success = true;
    if ($result2['skipped'] === 0) {
        echo "⚠️ ATTENTION: L'étape 2 devrait avoir ignoré des fiches existantes\n";
        $success = false;
    }
    if ($result3['deleted'] === 0 && $result3['created'] > 0) {
        echo "⚠️ ATTENTION: L'étape 3 devrait avoir supprimé des fiches avant de les recréer\n";
        $success = false;
    }
    if ($result4['deleted'] === 0 && $result4['created'] > 0) {
        echo "⚠️ ATTENTION: L'étape 4 devrait avoir supprimé des fiches avant de les recréer\n";
        $success = false;
    }
    
    if ($success) {
        echo "\n🎉 Tous les comportements sont corrects !\n";
    } else {
        echo "\n❌ Certains comportements ne sont pas conformes aux attentes.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur lors du test: " . $e->getMessage() . "\n";
    echo "💡 Assurez-vous que ERPNext est accessible et que les credentials sont corrects.\n";
    echo "📝 Trace: " . $e->getTraceAsString() . "\n";
}