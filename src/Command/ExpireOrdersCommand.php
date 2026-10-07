<?php

namespace App\Command;

use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Cron (toutes les minutes) : passe en « expirée » les paiements CB non aboutis dans le délai.
 * Les places sont déjà libres pour le calcul (expireLe dépassé) ; ce passage rend le statut lisible.
 */
#[AsCommand(name: 'app:orders:expire', description: 'Expire les paiements CB non aboutis dans le délai de blocage des places')]
final class ExpireOrdersCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $commandes,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expirees = $this->commandes->findExpiredPayments(new \DateTimeImmutable());
        foreach ($expirees as $commande) {
            $commande->expire();
        }
        $this->em->flush();

        $output->writeln(\sprintf('%d commande(s) expirée(s).', \count($expirees)), OutputInterface::VERBOSITY_VERBOSE);

        return Command::SUCCESS;
    }
}
