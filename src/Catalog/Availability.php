<?php

namespace App\Catalog;

use App\Entity\Session;
use App\Repository\OrderRepository;
use App\Smartof\SmartofClient;
use App\Smartof\SmartofException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Places restantes d'une session = limite SmartOF − inscrits SmartOF − places tenues par la boutique
 * (cf. CAHIER_DES_CHARGES.md §5.4). null = illimité.
 */
class Availability
{
    public function __construct(
        private readonly OrderRepository $commandes,
        private readonly SmartofClient $smartof,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<Session> $sessions
     *
     * @return array<int, ?int> places restantes par id de session
     */
    public function remainingSeats(array $sessions, ?\DateTimeImmutable $maintenant = null): array
    {
        $tenues = $this->commandes->heldSeats($sessions, $maintenant ?? new \DateTimeImmutable());
        $result = [];
        foreach ($sessions as $session) {
            $limite = $session->getLimitePlaces();
            $result[$session->getId()] = null === $limite
                ? null
                : max(0, $limite - $session->getInscritsSmartof() - ($tenues[$session->getId()] ?? 0));
        }

        return $result;
    }

    public function remainingSeatsForSession(Session $session, ?\DateTimeImmutable $maintenant = null): ?int
    {
        return $this->remainingSeats([$session], $maintenant)[$session->getId()];
    }

    /**
     * Relit le remplissage de la session en direct dans SmartOF, juste avant un paiement, pour tenir compte des
     * inscriptions faites directement dans SmartOF. En cas d'indisponibilité de l'API, on garde la dernière copie.
     */
    public function refresh(Session $session): void
    {
        try {
            $ouvertes = $this->smartof->sessionsOuvertes();
        } catch (SmartofException|\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
            $this->logger->warning('Remplissage SmartOF non relu, dernière synchro utilisée', ['session' => $session->getSmartofUid(), 'erreur' => $e->getMessage()]);

            return;
        }

        $session->setOuverte(false);
        foreach ($ouvertes as $ouverte) {
            if ($ouverte['session']['sessionUid'] === $session->getSmartofUid()) {
                $limite = $ouverte['remplissage']['limite'] ?? null;
                $session
                    ->setOuverte(true)
                    ->setInscritsSmartof((int) ($ouverte['remplissage']['inscrits'] ?? 0))
                    ->setLimitePlaces(is_numeric($limite) ? (int) $limite : null);
            }
        }
        $session->setSynchroniseLe(new \DateTimeImmutable());
        $this->em->flush();
    }
}
