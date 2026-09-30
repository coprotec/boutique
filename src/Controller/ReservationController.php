<?php

namespace App\Controller;

use App\Catalogue\Disponibilite;
use App\Catalogue\ReglesReservation;
use App\Entity\Financement;
use App\Entity\ModePaiement;
use App\Entity\Session;
use App\Notification\Notificateur;
use App\Repository\SessionRepository;
use App\Reservation\DemandeReservation;
use App\Reservation\ParticipantSaisi;
use App\Reservation\PlacesInsuffisantes;
use App\Reservation\Reservateur;
use App\Reservation\SocieteSaisie;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Tunnel de réservation : étape 1 (formulaire Vue) → étape 2 (récapitulatif, mode de paiement) → paiement.
 */
#[Route('/reservation/{id<\d+>}')]
final class ReservationController extends AbstractController
{
    private const CLE_SESSION = 'reservation_';

    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly ReglesReservation $regles,
        private readonly Disponibilite $disponibilite,
    ) {
    }

    #[Route('', name: 'reservation', methods: ['GET'])]
    public function formulaire(int $id, Request $request, NormalizerInterface $normalizer): Response
    {
        $session = $this->session($id);
        $formation = $session->getFormation();

        return $this->render('reservation/formulaire.html.twig', [
            'session' => $session,
            'config' => [
                'urlValidation' => $this->generateUrl('reservation_valider', ['id' => $id]),
                'csrf' => $this->container->get('security.csrf.token_manager')->getToken('reservation')->getValue(),
                'prixHtCentimes' => $formation->getPrixHtCentimes(),
                'tauxTva' => $formation->getTauxTva(),
                'placesRestantes' => $this->disponibilite->placesRestantesSession($session),
                'maxParticipants' => DemandeReservation::MAX_PARTICIPANTS,
                'eligibleCpf' => $formation->isEligibleCpf(),
                'organisations' => SocieteSaisie::ORGANISATIONS,
                'situations' => ParticipantSaisi::SITUATIONS,
                'financements' => array_map(static fn (Financement $f): array => ['valeur' => $f->value, 'libelle' => $f->libelle()], Financement::cases()),
                'saisie' => $request->getSession()->get(self::CLE_SESSION.$id),
                'nbInitial' => max(1, $request->query->getInt('participants', 1)),
            ],
        ]);
    }

    /** Validation de l'étape 1 (JSON). Erreurs : 422 au format « violations » de Symfony, lues par le composant Vue. */
    #[Route('', name: 'reservation_valider', methods: ['POST'], format: 'json')]
    public function valider(
        int $id,
        Request $request,
        #[MapRequestPayload(validationFailedStatusCode: 422)] DemandeReservation $demande,
        RateLimiterFactoryInterface $reservationLimiter,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('reservation', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['title' => 'Votre session a expiré, rechargez la page.'], 403);
        }
        if (!$reservationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->json(['title' => 'Trop de tentatives, réessayez dans quelques minutes.'], 429);
        }

        $session = $this->session($id);
        $restantes = $this->disponibilite->placesRestantesSession($session);
        if (null !== $restantes && \count($demande->participants) > $restantes) {
            return $this->json(['violations' => [[
                'propertyPath' => 'participants',
                'title' => (new PlacesInsuffisantes($restantes))->getMessage(),
            ]]], 422);
        }

        $request->getSession()->set(self::CLE_SESSION.$id, $request->toArray());

        return $this->json(['redirect' => $this->generateUrl('reservation_recapitulatif', ['id' => $id])]);
    }

    #[Route('/recapitulatif', name: 'reservation_recapitulatif', methods: ['GET'])]
    public function recapitulatif(int $id, Request $request, DenormalizerInterface $denormalizer, ValidatorInterface $validator): Response
    {
        $session = $this->session($id);
        $demande = $this->demandeEnSession($id, $request, $denormalizer, $validator);
        if (null === $demande) {
            return $this->redirectToRoute('reservation', ['id' => $id]);
        }

        $nb = \count($demande->participants);
        $prixHt = (int) $session->getFormation()->getPrixHtCentimes();

        return $this->render('reservation/recapitulatif.html.twig', [
            'session' => $session,
            'demande' => $demande,
            'modes' => $demande->financement->modesPaiement(),
            'totalHt' => $prixHt * $nb,
            'totalTtc' => (int) round($prixHt * $nb * (1 + $session->getFormation()->getTauxTva() / 100)),
        ]);
    }

    #[Route('/confirmer', name: 'reservation_confirmer', methods: ['POST'])]
    public function confirmer(
        int $id,
        Request $request,
        DenormalizerInterface $denormalizer,
        ValidatorInterface $validator,
        Reservateur $reservateur,
        Notificateur $notificateur,
    ): Response {
        if (!$this->isCsrfTokenValid('reservation_confirmer', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Votre session a expiré, merci de confirmer à nouveau.');

            return $this->redirectToRoute('reservation_recapitulatif', ['id' => $id]);
        }

        $session = $this->session($id);
        $demande = $this->demandeEnSession($id, $request, $denormalizer, $validator);
        $mode = ModePaiement::tryFrom($request->request->getString('mode'));
        if (null === $demande) {
            return $this->redirectToRoute('reservation', ['id' => $id]);
        }
        if (null === $mode || !\in_array($mode, $demande->financement->modesPaiement(), true) || !$request->request->getBoolean('consentement')) {
            $this->addFlash('danger', 'Choisissez un mode de paiement et acceptez les conditions pour continuer.');

            return $this->redirectToRoute('reservation_recapitulatif', ['id' => $id]);
        }

        try {
            $commande = $reservateur->reserver($session, $demande, $mode);
        } catch (PlacesInsuffisantes $e) {
            $this->addFlash('warning', $e->getMessage());

            return $this->redirectToRoute('reservation', ['id' => $id]);
        }

        $request->getSession()->remove(self::CLE_SESSION.$id);

        if ($mode->enLigne()) {
            return $this->redirectToRoute('paiement_cb', ['jeton' => $commande->getJeton()]);
        }

        $notificateur->commandeValidee($commande);

        return $this->redirectToRoute('commande', ['jeton' => $commande->getJeton()]);
    }

    private function session(int $id): Session
    {
        return $this->sessions->findReservable($id, $this->regles->debutMin())
            ?? throw new NotFoundHttpException('Cette session n\'est plus proposée à la réservation.');
    }

    private function demandeEnSession(int $id, Request $request, DenormalizerInterface $denormalizer, ValidatorInterface $validator): ?DemandeReservation
    {
        $donnees = $request->getSession()->get(self::CLE_SESSION.$id);
        if (!\is_array($donnees)) {
            return null;
        }

        $demande = $denormalizer->denormalize($donnees, DemandeReservation::class);

        return 0 === \count($validator->validate($demande)) ? $demande : null;
    }
}
