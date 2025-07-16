<?php

namespace App\Command;

use App\Service\ChangeHistoryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo-history',
    description: 'Démonstration du système d\'historique avec des scénarios réalistes',
)]
class DemoHistoryCommand extends Command
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
        $io->title('🎯 Démonstration du Système d\'Historique');

        // Scénario 1: Augmentation de salaire
        $io->section('📈 Scénario 1: Augmentation de salaire');
        $this->changeHistoryService->logPayslipChange(
            'SAL-2024-MARTIN-01',
            'base_salary',
            2500.00,
            2750.00,
            'Augmentation annuelle de 10% suite à l\'évaluation de performance'
        );
        
        $this->changeHistoryService->logPayslipChange(
            'SAL-2024-MARTIN-01',
            'transport_allowance',
            150.00,
            180.00,
            'Ajustement de l\'indemnité transport suite à l\'augmentation'
        );
        
        $io->success('Augmentation de salaire enregistrée pour Martin');

        // Scénario 2: Correction d'erreur
        $io->section('🔧 Scénario 2: Correction d\'erreur de saisie');
        $this->changeHistoryService->logPayslipChange(
            'SAL-2024-DUPONT-01',
            'overtime_hours',
            25.5,
            15.5,
            'Correction erreur de saisie - heures supplémentaires incorrectes'
        );
        
        $this->changeHistoryService->logPayslipChange(
            'SAL-2024-DUPONT-01',
            'overtime_amount',
            382.50,
            232.50,
            'Recalcul automatique suite à la correction des heures'
        );
        
        $io->success('Correction d\'erreur enregistrée pour Dupont');

        // Scénario 3: Modification de pourcentages saisonniers
        $io->section('🌟 Scénario 3: Ajustement des primes saisonnières');
        
        // Prime de fin d'année - augmentation en décembre
        $this->changeHistoryService->logMonthlyPercentageChange(
            'Prime fin d\'année',
            12, // Décembre
            5.0,
            8.0,
            'Augmentation de la prime de fin d\'année pour 2024'
        );
        
        // Prime d'été - réduction en août
        $this->changeHistoryService->logMonthlyPercentageChange(
            'Prime été',
            8, // Août
            3.0,
            2.0,
            'Ajustement de la prime d\'été suite aux nouvelles directives'
        );
        
        $io->success('Ajustements saisonniers enregistrés');

        // Scénario 4: Modification d'employé
        $io->section('👤 Scénario 4: Changement de statut employé');
        $this->changeHistoryService->logEmployeeChange(
            'EMP-BERNARD-2024',
            'employment_type',
            'CDD',
            'CDI',
            'Titularisation suite à la fin de période d\'essai'
        );
        
        $this->changeHistoryService->logEmployeeChange(
            'EMP-BERNARD-2024',
            'salary_structure',
            'Structure Stagiaire',
            'Structure Employé Confirmé',
            'Changement de structure salariale suite à la titularisation'
        );
        
        $io->success('Titularisation de Bernard enregistrée');

        // Scénario 5: Modification en lot
        $io->section('📊 Scénario 5: Modification en lot - Ajustement général');
        
        $employees = ['EMP-001', 'EMP-002', 'EMP-003', 'EMP-004', 'EMP-005'];
        foreach ($employees as $empId) {
            $this->changeHistoryService->logPayslipChange(
                "SAL-2024-{$empId}-01",
                'health_insurance',
                45.00,
                50.00,
                'Ajustement général des cotisations santé suite à la nouvelle convention'
            );
        }
        
        $io->success('Modification en lot appliquée à 5 employés');

        // Affichage des statistiques finales
        $io->section('📈 Statistiques après démonstration');
        
        $today = new \DateTime();
        $todayStart = (clone $today)->setTime(0, 0, 0);
        $todayEnd = (clone $today)->setTime(23, 59, 59);
        $todayStats = $this->changeHistoryService->getStatistics($todayStart, $todayEnd);
        
        if ($todayStats) {
            $totalToday = 0;
            foreach ($todayStats as $entityType => $actions) {
                $entityTotal = array_sum($actions);
                $totalToday += $entityTotal;
                $io->text("• {$entityType}: {$entityTotal} modification(s)");
            }
            $io->info("Total des modifications aujourd'hui: {$totalToday}");
        }

        // Récupérer les 10 dernières modifications
        $recentHistory = $this->changeHistoryService->getRecentHistory(10);
        
        if ($recentHistory) {
            $io->section('🕒 Dernières modifications');
            $tableData = [];
            foreach ($recentHistory as $change) {
                $tableData[] = [
                    $change->getChangedAt()->format('H:i:s'),
                    $change->getEntityType(),
                    $change->getEntityId(),
                    $change->getFieldName(),
                    $change->getActionLabel()
                ];
            }
            
            $io->table(
                ['Heure', 'Type', 'Entité', 'Champ', 'Action'],
                $tableData
            );
        }

        $io->success('🎉 Démonstration terminée avec succès !');
        $io->info('💡 Consultez l\'historique complet à l\'adresse: /history');
        $io->note('🔧 Utilisez "php bin/console app:clean-history --dry-run" pour voir les options de nettoyage');

        return Command::SUCCESS;
    }
}