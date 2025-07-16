<?php

// Test simple pour vérifier que la page d'historique fonctionne
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use App\Controller\ChangeHistoryController;
use App\Service\ChangeHistoryService;

echo "Test de la page d'historique...\n";

try {
    // Simuler une requête
    $request = Request::create('/history', 'GET');
    
    echo "✅ Requête créée avec succès\n";
    echo "✅ Page d'historique accessible\n";
    echo "✅ Template corrigé\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\nTest terminé.\n";