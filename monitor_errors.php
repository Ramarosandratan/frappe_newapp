<?php

/**
 * Script de monitoring des erreurs en temps réel
 */

echo "=== Monitoring des erreurs ===\n\n";

$logFile = '/home/rina/frappe_newapp/var/log/dev.log';

if (!file_exists($logFile)) {
    echo "❌ Fichier de log non trouvé: $logFile\n";
    exit(1);
}

// Fonction pour analyser une ligne de log
function analyzeLine($line) {
    $timestamp = '';
    $level = '';
    $message = '';
    
    // Extraire le timestamp
    if (preg_match('/\[([^\]]+)\]/', $line, $matches)) {
        $timestamp = $matches[1];
    }
    
    // Extraire le niveau
    if (preg_match('/\] app\.(\w+):/', $line, $matches)) {
        $level = $matches[1];
    }
    
    // Extraire le message
    if (preg_match('/\] app\.\w+: (.+?) \{/', $line, $matches)) {
        $message = $matches[1];
    }
    
    return [
        'timestamp' => $timestamp,
        'level' => $level,
        'message' => $message,
        'full_line' => $line
    ];
}

// Fonction pour formater l'affichage
function formatLogEntry($entry) {
    $levelColors = [
        'ERROR' => '🔴',
        'WARNING' => '🟡', 
        'INFO' => '🔵',
        'DEBUG' => '⚪'
    ];
    
    $icon = $levelColors[$entry['level']] ?? '⚫';
    $time = substr($entry['timestamp'], 11, 8); // Extraire HH:MM:SS
    
    return sprintf("%s %s [%s] %s", 
        $icon, 
        $time, 
        $entry['level'], 
        $entry['message']
    );
}

// Lire les dernières lignes du fichier
echo "📋 Dernières entrées du log:\n";
$lines = array_slice(file($logFile), -20);

$errorCount = 0;
$warningCount = 0;
$salarySlipErrors = 0;

foreach ($lines as $line) {
    $entry = analyzeLine(trim($line));
    
    if (empty($entry['level'])) continue;
    
    // Compter les erreurs
    if ($entry['level'] === 'ERROR') {
        $errorCount++;
        if (stripos($entry['message'], 'salary slip') !== false) {
            $salarySlipErrors++;
        }
    } elseif ($entry['level'] === 'WARNING') {
        $warningCount++;
    }
    
    // Afficher seulement les erreurs et avertissements liés aux fiches de paie
    if (($entry['level'] === 'ERROR' || $entry['level'] === 'WARNING') && 
        (stripos($entry['message'], 'salary') !== false || 
         stripos($entry['message'], 'slip') !== false ||
         stripos($entry['message'], 'cancelled') !== false)) {
        echo "  " . formatLogEntry($entry) . "\n";
    }
}

echo "\n📊 Statistiques des dernières 20 entrées:\n";
echo "  🔴 Erreurs totales: $errorCount\n";
echo "  🟡 Avertissements: $warningCount\n";
echo "  💼 Erreurs de fiches de paie: $salarySlipErrors\n\n";

// Analyser les types d'erreurs récentes
echo "🔍 Analyse des erreurs récentes:\n";

$recentErrors = [];
$content = file_get_contents($logFile);
$lines = explode("\n", $content);

// Chercher les erreurs des 2 dernières heures
$cutoffTime = time() - (2 * 3600); // 2 heures

foreach (array_reverse($lines) as $line) {
    if (empty(trim($line))) continue;
    
    $entry = analyzeLine($line);
    if ($entry['level'] !== 'ERROR') continue;
    
    // Vérifier si l'erreur est récente
    try {
        $logTime = new DateTime($entry['timestamp']);
        if ($logTime->getTimestamp() < $cutoffTime) break;
    } catch (Exception $e) {
        continue;
    }
    
    // Catégoriser l'erreur
    $message = $entry['message'];
    if (stripos($message, 'Cannot edit cancelled document') !== false) {
        $recentErrors['cancelled_document'] = ($recentErrors['cancelled_document'] ?? 0) + 1;
    } elseif (stripos($message, 'Cannot update cancelled salary slip') !== false) {
        $recentErrors['cancelled_slip_update'] = ($recentErrors['cancelled_slip_update'] ?? 0) + 1;
    } elseif (stripos($message, 'TimestampMismatchError') !== false) {
        $recentErrors['timestamp_mismatch'] = ($recentErrors['timestamp_mismatch'] ?? 0) + 1;
    } elseif (stripos($message, 'ValidationError') !== false) {
        $recentErrors['validation_error'] = ($recentErrors['validation_error'] ?? 0) + 1;
    } else {
        $recentErrors['other'] = ($recentErrors['other'] ?? 0) + 1;
    }
}

if (empty($recentErrors)) {
    echo "  ✅ Aucune erreur détectée dans les 2 dernières heures\n";
} else {
    $errorTypes = [
        'cancelled_document' => 'Documents annulés (ancien problème)',
        'cancelled_slip_update' => 'Tentative de mise à jour de fiches annulées',
        'timestamp_mismatch' => 'Erreurs de concurrence',
        'validation_error' => 'Erreurs de validation',
        'other' => 'Autres erreurs'
    ];
    
    foreach ($recentErrors as $type => $count) {
        $description = $errorTypes[$type] ?? $type;
        echo "  🔸 $description: $count occurrence(s)\n";
    }
}

echo "\n";

// Recommandations
echo "💡 Recommandations:\n";

if ($salarySlipErrors > 0) {
    echo "  ⚠️  Des erreurs de fiches de paie sont encore présentes\n";
    echo "  🔧 Vérifiez que les corrections ont été appliquées\n";
    echo "  📝 Consultez les logs détaillés pour plus d'informations\n";
} else {
    echo "  ✅ Aucune erreur de fiche de paie récente détectée\n";
    echo "  🎯 Le système semble fonctionner correctement\n";
}

echo "\n🛠️  Commandes utiles:\n";
echo "  # Surveiller en temps réel\n";
echo "  tail -f var/log/dev.log | grep -i 'salary\\|slip\\|error'\n\n";
echo "  # Compter les erreurs récentes\n";
echo "  grep -i 'error.*salary' var/log/dev.log | wc -l\n\n";
echo "  # Voir les dernières erreurs spécifiques\n";
echo "  grep -i 'Cannot edit cancelled' var/log/dev.log | tail -5\n\n";

echo "=== Monitoring terminé ===\n";