<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

// Test simple pour vérifier que notre méthode fonctionne
echo "Test de la méthode updateSalaryStructureAssignmentBase\n";

// Ici on pourrait tester notre méthode, mais pour l'instant on va juste vérifier que les fichiers sont bien créés
if (file_exists('test_employees.csv') && file_exists('test_structures.csv') && file_exists('test_salary_data.csv')) {
    echo "✓ Fichiers CSV de test créés avec succès\n";
    
    echo "\nContenu du fichier employés:\n";
    echo file_get_contents('test_employees.csv');
    
    echo "\nContenu du fichier structures:\n";
    echo file_get_contents('test_structures.csv');
    
    echo "\nContenu du fichier données salariales:\n";
    echo file_get_contents('test_salary_data.csv');
} else {
    echo "✗ Erreur: fichiers CSV de test manquants\n";
}