<?php

namespace App\Controller;

use App\Catalogue\Disponibilite;
use App\Catalogue\ReglesReservation;
use App\Repository\FormationRepository;
use App\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogueController extends AbstractController
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly FormationRepository $formations,
        private readonly Disponibilite $disponibilite,
        private readonly ReglesReservation $regles,
    ) {
    }

    #[Route('/', name: 'catalogue', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $debutMin = $this->regles->debutMin();
        $formation = $request->query->get('formation') ? $this->formations->findActiveBySlug($request->query->getString('formation')) : null;
        $mois = $request->query->getString('mois') ?: null;

        $sessions = $this->sessions->findReservables($debutMin, $formation, $mois);
        $toutes = $this->sessions->findReservables($debutMin);

        return $this->render('catalogue/index.html.twig', [
            'sessions' => $sessions,
            'places' => $this->disponibilite->placesRestantes($sessions),
            'formations' => $this->formations->findAvecSessionsReservables($debutMin),
            'mois' => $this->moisDisponibles($toutes),
            'filtre' => ['formation' => $formation?->getSlug(), 'mois' => $mois],
        ]);
    }

    /** Adresse stable par formation, pour les liens depuis le site vitrine (ex. /formation/t68-25). */
    #[Route('/formation/{slug}', name: 'formation', methods: ['GET'])]
    public function formation(string $slug): Response
    {
        $formation = $this->formations->findActiveBySlug($slug) ?? throw new NotFoundHttpException('Formation introuvable.');
        $sessions = $this->sessions->findReservables($this->regles->debutMin(), $formation);

        return $this->render('catalogue/formation.html.twig', [
            'formation' => $formation,
            'sessions' => $sessions,
            'places' => $this->disponibilite->placesRestantes($sessions),
        ]);
    }

    /**
     * @param list<\App\Entity\Session> $sessions
     *
     * @return list<\DateTimeImmutable> premier jour de chaque mois ayant au moins une session
     */
    private function moisDisponibles(array $sessions): array
    {
        $mois = [];
        foreach ($sessions as $session) {
            $mois[$session->getDateDebut()->format('Y-m')] = $session->getDateDebut()->modify('first day of this month')->setTime(0, 0);
        }

        return array_values($mois);
    }
}
