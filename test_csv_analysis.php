<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\File\UploadedFile;
use League\Csv\Reader;

// Test de création de fichiers CSV de test
function createTestEmployeeFile(): string
{
    $filename = sys_get_temp_dir() . '/test_employees.csv';
    $data = [
        ['Ref', 'Nom', 'Prenom', 'genre', 'Date embauche', 'date naissance', 'company'],
        ['EMP001', 'Dupont', 'Jean', 'Male', '01/01/2020', '15/05/1985', 'Test Company'],
        ['EMP002', 'Martin', 'Marie', 'Female', '15/03/2021', '22/08/1990', 'Test Company'],
        ['EMP003', 'Durand', 'Pierre', 'Male', '10/06/2019', '03/12/1982', 'Another Company'],
    ];
    
    $file = fopen($filename, 'w');
    foreach ($data as $row) {
        fputcsv($file, $row);
    }
    fclose($file);
    
    return $filename;
}

function createTestStructureFile(): string
{
    $filename = sys_get_temp_dir() . '/test_structures.csv';
    $data = [
        ['salary structure', 'name', 'Abbr', 'type', 'valeur', 'company'],
        ['Standard Salary', 'Basic Salary', 'BS', 'earning', 'base', 'Test Company'],
        ['Standard Salary', 'Transport Allowance', 'TA', 'earning', '5000', 'Test Company'],
        ['Standard Salary', 'Tax', 'TAX', 'deduction', 'base * 0.1', 'Test Company'],
        ['Manager Salary', 'Basic Salary', 'BS', 'earning', 'base', 'Another Company'],
    ];
    
    $file = fopen($filename, 'w');
    foreach ($data as $row) {
        fputcsv($file, $row);
    }
    fclose($file);
    
    return $filename;
}

function createTestSalaryDataFile(): string
{
    $filename = sys_get_temp_dir() . '/test_salary_data.csv';
    $data = [
        ['Mois', 'Ref Employe', 'Salaire Base', 'Salaire'],
        ['01/2024', 'EMP001', '50000', 'Standard Salary'],
        ['01/2024', 'EMP002', '45000', 'Standard Salary'],
        ['01/2024', 'EMP003', '60000', 'Manager Salary'],
        ['01/2024', 'EMP999', '40000', 'Unknown Salary'], // Employé inexistant
    ];
    
    $file = fopen($filename, 'w');
    foreach ($data as $row) {
        fputcsv($file, $row);
    }
    fclose($file);
    
    return $filename;
}

// Test de l'analyse des fichiers
echo "=== Test de l'analyse des fichiers CSV ===\n\n";

$employeeFile = createTestEmployeeFile();
$structureFile = createTestStructureFile();
$salaryDataFile = createTestSalaryDataFile();

echo "Fichiers de test créés :\n";
echo "- Employés : $employeeFile\n";
echo "- Structures : $structureFile\n";
echo "- Données salariales : $salaryDataFile\n\n";

// Test de lecture des fichiers
echo "=== Analyse du fichier employés ===\n";
$csv = Reader::createFromPath($employeeFile, 'r');
$csv->setHeaderOffset(0);
$records = iterator_to_array($csv->getRecords());
echo "Nombre d'employés : " . count($records) . "\n";
echo "En-têtes : " . implode(', ', $csv->getHeader()) . "\n\n";

echo "=== Analyse du fichier structures ===\n";
$csv = Reader::createFromPath($structureFile, 'r');
$csv->setHeaderOffset(0);
$records = iterator_to_array($csv->getRecords());
echo "Nombre de composants : " . count($records) . "\n";
echo "En-têtes : " . implode(', ', $csv->getHeader()) . "\n\n";

echo "=== Analyse du fichier données salariales ===\n";
$csv = Reader::createFromPath($salaryDataFile, 'r');
$csv->setHeaderOffset(0);
$records = iterator_to_array($csv->getRecords());
echo "Nombre d'enregistrements : " . count($records) . "\n";
echo "En-têtes : " . implode(', ', $csv->getHeader()) . "\n\n";

// Simulation de l'analyse croisée
echo "=== Analyse croisée ===\n";
$employeeRefs = ['EMP001', 'EMP002', 'EMP003'];
$salaryEmployees = ['EMP001', 'EMP002', 'EMP003', 'EMP999'];
$definedStructures = ['Standard Salary', 'Manager Salary'];
$usedStructures = ['Standard Salary', 'Manager Salary', 'Unknown Salary'];

$missingEmployees = array_diff($salaryEmployees, $employeeRefs);
$orphanedStructures = array_diff($usedStructures, $definedStructures);

echo "Employés manquants : " . implode(', ', $missingEmployees) . "\n";
echo "Structures orphelines : " . implode(', ', $orphanedStructures) . "\n";

// Nettoyage
unlink($employeeFile);
unlink($structureFile);
unlink($salaryDataFile);

echo "\nTest terminé avec succès !\n";