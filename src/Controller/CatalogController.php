<?php

namespace App\Controller;

use App\Catalog\Availability;
use App\Catalog\BookingRules;
use App\Repository\CourseRepository;
use App\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogController extends AbstractController
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly CourseRepository $formations,
        private readonly Availability $disponibilite,
        private readonly BookingRules $regles,
    ) {
    }

    #[Route('/', name: 'catalogue', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $debutMin = $this->regles->minStartDate();
        $formation = $request->query->get('formation') ? $this->formations->findActiveBySlug($request->query->getString('formation')) : null;
        $mois = $request->query->getString('mois') ?: null;

        $sessions = $this->sessions->findBookables($debutMin, $formation, $mois);
        $toutes = $this->sessions->findBookables($debutMin);

        return $this->render('catalogue/index.html.twig', [
            'sessions' => $sessions,
            'places' => $this->disponibilite->remainingSeats($sessions),
            'formations' => $this->formations->findWithBookableSessions($debutMin),
            'mois' => $this->availableMonths($toutes),
            'filtre' => ['formation' => $formation?->getSlug(), 'mois' => $mois],
        ]);
    }

    /** Adresse stable par formation, pour les liens depuis le site vitrine (ex. /formation/t68-25). */
    #[Route('/formation/{slug}', name: 'formation', methods: ['GET'])]
    public function course(string $slug): Response
    {
        $formation = $this->formations->findActiveBySlug($slug) ?? throw new NotFoundHttpException('Formation introuvable.');
        $sessions = $this->sessions->findBookables($this->regles->minStartDate(), $formation);

        return $this->render('catalogue/formation.html.twig', [
            'formation' => $formation,
            'sessions' => $sessions,
            'places' => $this->disponibilite->remainingSeats($sessions),
        ]);
    }

    /**
     * @param list<\App\Entity\Session> $sessions
     *
     * @return list<\DateTimeImmutable> premier jour de chaque mois ayant au moins une session
     */
    private function availableMonths(array $sessions): array
    {
        $mois = [];
        foreach ($sessions as $session) {
            $mois[$session->getDateDebut()->format('Y-m')] = $session->getDateDebut()->modify('first day of this month')->setTime(0, 0);
        }

        return array_values($mois);
    }
}
