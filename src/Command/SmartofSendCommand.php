<?php

namespace App\Command;

use App\Entity\OrderStatus;
use App\Repository\OrderRepository;
use App\Smartof\EnrollmentSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cron (toutes les 5 minutes) : envoie à SmartOF les commandes validées en attente ou à relancer.
 * Avec un n° de commande : relance manuelle après correction (cf. email d'alerte).
 */
#[AsCommand(name: 'app:smartof:send', description: 'Transmet à SmartOF les commandes validées non encore inscrites')]
final class SmartofSendCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $commandes,
        private readonly EnrollmentSender $transmetteur,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('numero', InputArgument::OPTIONAL, 'N° de commande à relancer manuellement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null !== $numero = $input->getArgument('numero')) {
            $commande = $this->commandes->findOneBy(['numero' => $numero]);
            if (null === $commande || OrderStatus::Validee !== $commande->getStatut()) {
                $io->error("Commande $numero introuvable ou non validée.");

                return Command::FAILURE;
            }
            $commandes = [$commande];
        } else {
            $commandes = $this->commandes->findToSend(new \DateTimeImmutable());
        }

        $echecs = 0;
        foreach ($commandes as $commande) {
            if (!$this->transmetteur->send($commande)) {
                ++$echecs;
                $io->warning(\sprintf('%s : %s', $commande->getNumero(), $commande->getDerniereErreur()));
            }
        }

        if ([] !== $commandes) {
            $io->writeln(\sprintf('%d commande(s) traitée(s), %d échec(s).', \count($commandes), $echecs));
        }

        return 0 === $echecs ? Command::SUCCESS : Command::FAILURE;
    }
}
