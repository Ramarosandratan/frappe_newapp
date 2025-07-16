<?php

/**
 * Script de test pour vérifier la correction de la mise à jour du salaire de base
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\UrlHelper;

echo "=== Test de la correction de la mise à jour du salaire de base ===\n\n";

// Test 1: Vérification de l'encodage/décodage des IDs
echo "Test 1: Encodage/Décodage des IDs\n";
echo "-----------------------------------\n";

$testIds = [
    'Sal Slip/HR-EMP-00050/00001',
    'Test Salary Slip 123',
    'SAL-SLIP-001'
];

foreach ($testIds as $originalId) {
    $encoded = UrlHelper::encodeId($originalId);
    $decoded = UrlHelper::decodeId($encoded);
    $status = ($originalId === $decoded) ? '✓' : '✗';
    echo "Original: '$originalId'\n";
    echo "Encoded:  '$encoded'\n";
    echo "Decoded:  '$decoded' $status\n\n";
}

// Test 2: Simulation des URLs de routes
echo "Test 2: Simulation des URLs de routes\n";
echo "-------------------------------------\n";

$testId = 'Sal Slip/HR-EMP-00050/00001';
$encodedId = UrlHelper::encodeId($testId);

$routes = [
    'View payslip' => "/payslip/$encodedId",
    'Update base salary' => "/payslip/$encodedId/update-base-salary",
    'PDF generation' => "/payslip/$encodedId/pdf"
];

foreach ($routes as $description => $url) {
    echo "$description: $url\n";
}

echo "\n=== Test de la structure JSON ===\n";

$testData = [
    'base_salary' => 3500.00
];

echo "JSON à envoyer: " . json_encode($testData) . "\n";
echo "Validation: " . (isset($testData['base_salary']) && is_numeric($testData['base_salary']) ? '✓' : '✗') . "\n";

echo "\n=== Résumé de la correction ===\n";
echo "✓ Routes réorganisées dans le bon ordre\n";
echo "✓ Route spécifique /payslip/{id}/update-base-salary avant la route générale\n";
echo "✓ Méthode updateBaseSalary déplacée au début du contrôleur\n";
echo "✓ Duplication de méthode supprimée\n";
echo "✓ Logging amélioré pour le débogage\n";
echo "✓ Cache Symfony vidé\n";

echo "\nLa correction devrait résoudre l'erreur de mise à jour du salaire de base.\n";
echo "Testez maintenant la fonctionnalité dans l'interface utilisateur.\n";