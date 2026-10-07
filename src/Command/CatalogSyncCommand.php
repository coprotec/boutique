<?php

namespace App\Command;

use App\Catalog\CatalogSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lancée chaque matin par cron, et à la demande (ex. dès qu'une session est publiée dans SmartOF).
 */
#[AsCommand(name: 'app:catalog:sync', description: 'Synchronise les formations COPROTEC et leurs sessions depuis SmartOF')]
final class CatalogSyncCommand extends Command
{
    public function __construct(private readonly CatalogSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rapport = $this->synchronizer->synchronize();

        $io->success(\sprintf(
            '%d formation(s), %d session(s) à venir dont %d ouverte(s). Retirées : %d formation(s), %d session(s) fermée(s).',
            $rapport->formations,
            $rapport->sessions,
            $rapport->sessionsOuvertes,
            $rapport->formationsRetirees,
            $rapport->sessionsFermees,
        ));

        return Command::SUCCESS;
    }
}
