<?php

namespace App\Repository;

use App\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    /** @return array<string, Course> indexées par UID SmartOF */
    public function findAllIndexedBySmartofUid(): array
    {
        return $this->createQueryBuilder('f', 'f.smartofUid')->getQuery()->getResult();
    }

    public function findActiveBySlug(string $slug): ?Course
    {
        return $this->findOneBy(['slug' => $slug, 'active' => true]);
    }

    /** @return list<Course> formations actives ayant au moins une session réservable, pour le filtre du catalogue */
    public function findWithBookableSessions(\DateTimeImmutable $debutMin): array
    {
        return $this->createQueryBuilder('f')
            ->innerJoin('f.sessions', 's')
            ->andWhere('f.active = true')
            ->andWhere('s.ouverte = true')
            ->andWhere('s.dateDebut >= :debutMin')
            ->setParameter('debutMin', $debutMin)
            ->orderBy('f.intitule', 'ASC')
            ->distinct()
            ->getQuery()
            ->getResult();
    }
}
