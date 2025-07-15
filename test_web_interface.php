<?php

/**
 * Test de l'interface web pour les pourcentages mensuels
 */

echo "=== Test de l'interface web ===\n\n";

// Simuler une requête POST avec les pourcentages mensuels
$postData = [
    'component' => 'Salaire Base',
    'start_date' => '2025-03-01',
    'end_date' => '2025-03-31',
    'use_monthly_percentages' => '1',
    'monthly_percentages' => [
        1 => '5.0',   // Janvier: +5%
        2 => '3.0',   // Février: +3%
        3 => '-2.0',  // Mars: -2%
        4 => '0.0',   // Avril: 0%
        5 => '10.0',  // Mai: +10%
        6 => '7.5',   // Juin: +7.5%
        7 => '2.5',   // Juillet: +2.5%
        8 => '0.0',   // Août: 0%
        9 => '4.0',   // Septembre: +4%
        10 => '6.0',  // Octobre: +6%
        11 => '8.0',  // Novembre: +8%
        12 => '12.0'  // Décembre: +12%
    ]
];

echo "📋 Données de test:\n";
echo "   Composant: {$postData['component']}\n";
echo "   Période: {$postData['start_date']} à {$postData['end_date']}\n";
echo "   Pourcentages mensuels: Activés\n";
echo "   Pourcentage pour Mars: {$postData['monthly_percentages'][3]}%\n\n";

// Construire la requête curl
$curlCommand = 'curl -s -X POST "http://127.0.0.1:8001/salary/modifier" \\' . "\n";
$curlCommand .= '  -H "Content-Type: application/x-www-form-urlencoded" \\' . "\n";
$curlCommand .= '  -d "' . http_build_query($postData) . '"';

echo "🌐 Commande curl générée:\n";
echo "$curlCommand\n\n";

// Exécuter la requête
echo "🚀 Exécution de la requête...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8001/salary/modifier');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Erreur curl: $error\n";
    exit(1);
}

echo "📊 Réponse HTTP: $httpCode\n";

if ($httpCode === 200) {
    echo "✅ Requête réussie!\n\n";
    
    // Analyser la réponse HTML pour extraire les informations importantes
    if (strpos($response, 'fiches de paie modifiées avec succès') !== false) {
        echo "🎯 Résultat: Modifications appliquées avec succès\n";
    } elseif (strpos($response, 'Aucune fiche de paie modifiée') !== false) {
        echo "⚠️  Résultat: Aucune fiche modifiée\n";
        
        // Extraire les détails
        if (preg_match('/(\d+) ignorées \(condition non respectée\)/', $response, $matches)) {
            echo "   Fiches ignorées: {$matches[1]}\n";
        }
        
        if (preg_match('/(\d+) erreurs/', $response, $matches)) {
            echo "   Erreurs: {$matches[1]}\n";
        }
    }
    
    // Chercher des messages d'erreur spécifiques
    if (strpos($response, 'InvalidArgumentException') !== false) {
        echo "❌ Erreur de validation détectée\n";
    }
    
    if (strpos($response, 'RuntimeException') !== false) {
        echo "❌ Erreur d'exécution détectée\n";
    }
    
} else {
    echo "❌ Erreur HTTP: $httpCode\n";
    echo "Réponse: " . substr($response, 0, 500) . "...\n";
}

echo "\n📝 Vérification des logs:\n";

// Vérifier les logs récents
$logFile = '/home/rina/frappe_newapp/var/log/dev.log';
if (file_exists($logFile)) {
    $logLines = file($logFile);
    $recentLines = array_slice($logLines, -20);
    
    $relevantLogs = [];
    foreach ($recentLines as $line) {
        if (stripos($line, 'salary') !== false || 
            stripos($line, 'monthly') !== false || 
            stripos($line, 'percentage') !== false ||
            stripos($line, 'modified') !== false) {
            $relevantLogs[] = trim($line);
        }
    }
    
    if (!empty($relevantLogs)) {
        echo "Logs récents pertinents:\n";
        foreach (array_slice($relevantLogs, -5) as $log) {
            echo "  " . substr($log, 0, 100) . "...\n";
        }
    } else {
        echo "Aucun log récent pertinent trouvé\n";
    }
} else {
    echo "Fichier de log non trouvé\n";
}

echo "\n=== Test terminé ===\n";