<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Session;
use App\Entity\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Places tenues par la boutique et pas encore comptées dans les inscrits SmartOF de la session :
     * paiements CB en cours non expirés, et commandes validées non transmises ou transmises après la dernière
     * lecture de SmartOF (Session::$synchroniseLe).
     *
     * @param list<Session> $sessions
     *
     * @return array<int, int> places par id de session
     */
    public function heldSeats(array $sessions, \DateTimeImmutable $maintenant): array
    {
        if ([] === $sessions) {
            return [];
        }

        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.session) AS session, SUM(c.nbParticipants) AS places')
            ->innerJoin('c.session', 's')
            ->andWhere('c.session IN (:sessions)')
            ->andWhere('(c.statut = :enCours AND c.expireLe > :maintenant) OR (c.statut = :validee AND (c.transmiseLe IS NULL OR c.transmiseLe > s.synchroniseLe))')
            ->setParameter('sessions', $sessions)
            ->setParameter('enCours', OrderStatus::PaiementEnCours)
            ->setParameter('validee', OrderStatus::Validee)
            ->setParameter('maintenant', $maintenant)
            ->groupBy('c.session')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $r): array => [(int) $r['session'], (int) $r['places']], $rows), 1, 0);
    }

    /** @return list<Order> paiements CB dont le blocage de places a expiré */
    public function findExpiredPayments(\DateTimeImmutable $maintenant): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :enCours AND c.expireLe <= :maintenant')
            ->setParameter('enCours', OrderStatus::PaiementEnCours)
            ->setParameter('maintenant', $maintenant)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Order> commandes validées à (re)transmettre à SmartOF */
    public function findToSend(\DateTimeImmutable $maintenant, int $limite = 50): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :validee AND c.transmiseLe IS NULL')
            ->andWhere('c.prochaineTentativeLe IS NOT NULL AND c.prochaineTentativeLe <= :maintenant')
            ->setParameter('validee', OrderStatus::Validee)
            ->setParameter('maintenant', $maintenant)
            ->orderBy('c.valideeLe', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    public function findOneByToken(string $jeton): ?Order
    {
        return $this->findOneBy(['jeton' => $jeton]);
    }
}
