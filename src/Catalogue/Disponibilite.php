<?php

namespace App\Catalogue;

use App\Entity\Session;
use App\Repository\CommandeRepository;
use App\Smartof\SmartofClient;
use App\Smartof\SmartofException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Places restantes d'une session = limite SmartOF − inscrits SmartOF − places tenues par la boutique
 * (cf. CAHIER_DES_CHARGES.md §5.4). null = illimité.
 */
class Disponibilite
{
    public function __construct(
        private readonly CommandeRepository $commandes,
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
    public function placesRestantes(array $sessions, ?\DateTimeImmutable $maintenant = null): array
    {
        $tenues = $this->commandes->placesTenues($sessions, $maintenant ?? new \DateTimeImmutable());
        $result = [];
        foreach ($sessions as $session) {
            $limite = $session->getLimitePlaces();
            $result[$session->getId()] = null === $limite
                ? null
                : max(0, $limite - $session->getInscritsSmartof() - ($tenues[$session->getId()] ?? 0));
        }

        return $result;
    }

    public function placesRestantesSession(Session $session, ?\DateTimeImmutable $maintenant = null): ?int
    {
        return $this->placesRestantes([$session], $maintenant)[$session->getId()];
    }

    /**
     * Relit le remplissage de la session en direct dans SmartOF, juste avant un paiement, pour tenir compte des
     * inscriptions faites directement dans SmartOF. En cas d'indisponibilité de l'API, on garde la dernière copie.
     */
    public function rafraichir(Session $session): void
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
