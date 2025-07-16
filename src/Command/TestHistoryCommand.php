<?php

namespace App\Command;

use App\Service\ChangeHistoryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-history',
    description: 'Teste le système d\'historique en créant des données de test',
)]
class TestHistoryCommand extends Command
{
    private ChangeHistoryService $changeHistoryService;

    public function __construct(ChangeHistoryService $changeHistoryService)
    {
        $this->changeHistoryService = $changeHistoryService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test du système d\'historique');

        // Test 1: Modification d'une fiche de paie
        $io->section('Test 1: Modification de fiche de paie');
        $this->changeHistoryService->logPayslipChange(
            'SAL-2024-001',
            'Salaire de base',
            2500.00,
            2800.00,
            'Test de modification de salaire de base'
        );
        $io->success('Modification de fiche de paie enregistrée');

        // Test 2: Modification de pourcentage mensuel
        $io->section('Test 2: Modification de pourcentage mensuel');
        $this->changeHistoryService->logMonthlyPercentageChange(
            'Prime transport',
            3, // Mars
            10.0,
            15.0,
            'Test de modification de pourcentage mensuel'
        );
        $io->success('Modification de pourcentage mensuel enregistrée');

        // Test 3: Création d'employé
        $io->section('Test 3: Création d\'employé');
        $this->changeHistoryService->logEmployeeChange(
            'EMP-001',
            'status',
            null,
            'Active',
            'Création d\'un nouvel employé'
        );
        $io->success('Création d\'employé enregistrée');

        // Test 4: Modification générique
        $io->section('Test 4: Modification générique');
        $this->changeHistoryService->logChange(
            'Company',
            'COMP-001',
            'name',
            'Ancienne Société',
            'Nouvelle Société',
            'UPDATE',
            'Test de modification générique'
        );
        $io->success('Modification générique enregistrée');

        // Afficher les statistiques
        $io->section('Statistiques');
        $statistics = $this->changeHistoryService->getStatistics();
        
        if ($statistics) {
            $io->table(
                ['Type d\'entité', 'Créations', 'Modifications', 'Suppressions', 'Total'],
                array_map(function($entityType, $actions) {
                    return [
                        $entityType,
                        $actions['CREATE'] ?? 0,
                        $actions['UPDATE'] ?? 0,
                        $actions['DELETE'] ?? 0,
                        array_sum($actions)
                    ];
                }, array_keys($statistics), $statistics)
            );
        } else {
            $io->info('Aucune statistique disponible');
        }

        $io->success('Tests terminés avec succès !');
        $io->info('Vous pouvez maintenant consulter l\'historique à l\'adresse: /history');

        return Command::SUCCESS;
    }
}