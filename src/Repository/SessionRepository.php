<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /** @return array<string, Session> indexées par UID SmartOF */
    public function findAllIndexedBySmartofUid(): array
    {
        return $this->createQueryBuilder('s', 's.smartofUid')->getQuery()->getResult();
    }

    /**
     * Sessions affichées au catalogue : formation active, inscriptions ouvertes dans SmartOF, début après la date limite.
     *
     * @return list<Session>
     */
    public function findBookables(\DateTimeImmutable $debutMin, ?Course $formation = null, ?string $mois = null): array
    {
        $qb = $this->bookable($debutMin);

        if (null !== $formation) {
            $qb->andWhere('s.formation = :formation')->setParameter('formation', $formation);
        }

        if (null !== $mois && 1 === preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $debutMois = new \DateTimeImmutable($mois.'-01 00:00:00');
            $qb->andWhere('s.dateDebut >= :debutMois AND s.dateDebut < :finMois')
                ->setParameter('debutMois', $debutMois)
                ->setParameter('finMois', $debutMois->modify('+1 month'));
        }

        return $qb->getQuery()->getResult();
    }

    public function findBookable(int $id, \DateTimeImmutable $debutMin): ?Session
    {
        return $this->bookable($debutMin)
            ->andWhere('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function bookable(\DateTimeImmutable $debutMin): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->addSelect('f')
            ->innerJoin('s.formation', 'f')
            ->andWhere('f.active = true')
            ->andWhere('s.ouverte = true')
            ->andWhere('s.dateDebut >= :debutMin')
            ->setParameter('debutMin', $debutMin)
            ->orderBy('s.dateDebut', 'ASC');
    }
}
