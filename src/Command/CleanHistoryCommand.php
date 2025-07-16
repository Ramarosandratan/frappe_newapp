<?php

namespace App\Command;

use App\Service\ChangeHistoryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-history',
    description: 'Nettoie l\'historique des modifications ancien',
)]
class CleanHistoryCommand extends Command
{
    private ChangeHistoryService $changeHistoryService;

    public function __construct(ChangeHistoryService $changeHistoryService)
    {
        $this->changeHistoryService = $changeHistoryService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('days', InputArgument::OPTIONAL, 'Nombre de jours à conserver', 365)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher ce qui serait supprimé sans le faire')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getArgument('days');
        $dryRun = $input->getOption('dry-run');

        $io->title('Nettoyage de l\'historique des modifications');

        if ($dryRun) {
            $io->note('Mode simulation activé - aucune suppression ne sera effectuée');
        }

        $io->info(sprintf('Suppression des modifications plus anciennes que %d jours', $days));

        // Compter d'abord combien d'enregistrements seraient affectés
        $countToDelete = $this->changeHistoryService->countOldHistory($days);
        
        if ($countToDelete === 0) {
            $io->info('Aucun enregistrement à supprimer');
            return Command::SUCCESS;
        }

        $io->info(sprintf('%d enregistrement(s) seront supprimé(s)', $countToDelete));

        if (!$dryRun) {
            if (!$io->confirm('Êtes-vous sûr de vouloir supprimer ces enregistrements ?', false)) {
                $io->info('Opération annulée');
                return Command::SUCCESS;
            }

            $deletedCount = $this->changeHistoryService->cleanOldHistory($days);
            
            if ($deletedCount > 0) {
                $io->success(sprintf('%d enregistrement(s) supprimé(s) avec succès', $deletedCount));
            } else {
                $io->warning('Aucun enregistrement n\'a été supprimé');
            }
        } else {
            $io->info('Mode simulation - utilisez sans --dry-run pour effectuer la suppression');
        }

        return Command::SUCCESS;
    }
}