<?php

namespace App\Controller;

use App\Catalog\Availability;
use App\Catalog\BookingRules;
use App\Entity\Funding;
use App\Entity\PaymentMethod;
use App\Entity\Session;
use App\Notification\Notifier;
use App\Repository\SessionRepository;
use App\Reservation\ReservationRequest;
use App\Reservation\ParticipantInput;
use App\Reservation\InsufficientSeatsException;
use App\Reservation\ReservationService;
use App\Reservation\CompanyInput;
use App\Reservation\Formats;
use App\Smartof\EnrollmentSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Intl\Countries;
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
        private readonly BookingRules $regles,
        private readonly Availability $disponibilite,
    ) {
    }

    #[Route('', name: 'reservation', methods: ['GET'])]
    public function form(int $id, Request $request): Response
    {
        $session = $this->findSession($id);
        $formation = $session->getFormation();

        return $this->render('reservation/formulaire.html.twig', [
            'session' => $session,
            'config' => [
                'urlValidation' => $this->generateUrl('reservation_valider', ['id' => $id]),
                'csrf' => $this->container->get('security.csrf.token_manager')->getToken('reservation')->getValue(),
                'prixHtCentimes' => $formation->getPrixHtCentimes(),
                'tauxTva' => $formation->getTauxTva(),
                'placesRestantes' => $this->disponibilite->remainingSeatsForSession($session),
                'maxParticipants' => ReservationRequest::MAX_PARTICIPANTS,
                'eligibleCpf' => $formation->isEligibleCpf(),
                'organisations' => CompanyInput::ORGANISATIONS,
                'pays' => $this->countries(),
                'situations' => ParticipantInput::SITUATIONS,
                'financements' => array_map(static fn (Funding $f): array => ['valeur' => $f->value, 'libelle' => $f->label()], Funding::cases()),
                'saisie' => $request->getSession()->get(self::CLE_SESSION.$id),
                'nbInitial' => max(1, $request->query->getInt('participants', 1)),
            ],
        ]);
    }

    /** Validation de l'étape 1 (JSON). Erreurs : 422 au format « violations » de Symfony, lues par le composant Vue. */
    #[Route('', name: 'reservation_valider', methods: ['POST'], format: 'json')]
    public function validate(
        int $id,
        Request $request,
        #[MapRequestPayload(validationFailedStatusCode: 422)] ReservationRequest $demande,
        RateLimiterFactoryInterface $reservationLimiter,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('reservation', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['title' => 'Votre session a expiré, rechargez la page.'], 403);
        }
        if (!$reservationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->json(['title' => 'Trop de tentatives, réessayez dans quelques minutes.'], 429);
        }

        $session = $this->findSession($id);
        $restantes = $this->disponibilite->remainingSeatsForSession($session);
        if (null !== $restantes && \count($demande->getParticipants()) > $restantes) {
            return $this->json(['violations' => [[
                'propertyPath' => 'participants',
                'title' => (new InsufficientSeatsException($restantes))->getMessage(),
            ]]], 422);
        }

        $request->getSession()->set(self::CLE_SESSION.$id, $request->toArray());

        return $this->json(['redirect' => $this->generateUrl('reservation_recapitulatif', ['id' => $id])]);
    }

    #[Route('/recapitulatif', name: 'reservation_recapitulatif', methods: ['GET'])]
    public function summary(int $id, Request $request, DenormalizerInterface $denormalizer, ValidatorInterface $validator): Response
    {
        $session = $this->findSession($id);
        $demande = $this->requestFromSession($id, $request, $denormalizer, $validator);
        if (null === $demande) {
            return $this->redirectToRoute('reservation', ['id' => $id]);
        }

        $nb = \count($demande->getParticipants());
        $prixHt = (int) $session->getFormation()->getPrixHtCentimes();

        return $this->render('reservation/recapitulatif.html.twig', [
            'session' => $session,
            'demande' => $demande,
            'modes' => $demande->financement->paymentMethods(),
            'totalHt' => $prixHt * $nb,
            'totalTtc' => (int) round($prixHt * $nb * (1 + $session->getFormation()->getTauxTva() / 100)),
        ]);
    }

    #[Route('/confirmer', name: 'reservation_confirmer', methods: ['POST'])]
    public function confirm(
        int $id,
        Request $request,
        DenormalizerInterface $denormalizer,
        ValidatorInterface $validator,
        ReservationService $reservateur,
        Notifier $notificateur,
        EnrollmentSender $transmetteur,
    ): Response {
        if (!$this->isCsrfTokenValid('reservation_confirmer', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Votre session a expiré, merci de confirmer à nouveau.');

            return $this->redirectToRoute('reservation_recapitulatif', ['id' => $id]);
        }

        $session = $this->findSession($id);
        $demande = $this->requestFromSession($id, $request, $denormalizer, $validator);
        $mode = PaymentMethod::tryFrom($request->request->getString('mode'));
        if (null === $demande) {
            return $this->redirectToRoute('reservation', ['id' => $id]);
        }
        if (null === $mode || !\in_array($mode, $demande->financement->paymentMethods(), true) || !$request->request->getBoolean('consentement')) {
            $this->addFlash('danger', 'Choisissez un mode de paiement et acceptez les conditions pour continuer.');

            return $this->redirectToRoute('reservation_recapitulatif', ['id' => $id]);
        }

        try {
            $commande = $reservateur->reserve($session, $demande, $mode);
        } catch (InsufficientSeatsException $e) {
            $this->addFlash('warning', $e->getMessage());

            return $this->redirectToRoute('reservation', ['id' => $id]);
        }

        $request->getSession()->remove(self::CLE_SESSION.$id);

        if ($mode->isOnline()) {
            return $this->redirectToRoute('paiement_cb', ['jeton' => $commande->getJeton()]);
        }

        // Virement, chèque, France Travail : commande validée d'emblée (PROVISOIRE, QE-8), inscrite dans SmartOF tout de suite ;
        // en cas d'échec, le cron app:smartof:send relance.
        $notificateur->orderValidated($commande);
        $transmetteur->send($commande);

        return $this->redirectToRoute('commande', ['jeton' => $commande->getJeton()]);
    }

    /** @return list<array{valeur: string, libelle: string}> France en tête, puis ordre alphabétique français */
    private function countries(): array
    {
        $pays = [Formats::PAYS_DEFAUT => Countries::getName(Formats::PAYS_DEFAUT, 'fr')] + Countries::getNames('fr');

        return array_map(static fn (string $code, string $nom): array => ['valeur' => $code, 'libelle' => $nom], array_keys($pays), $pays);
    }

    private function findSession(int $id): Session
    {
        return $this->sessions->findBookable($id, $this->regles->minStartDate())
            ?? throw new NotFoundHttpException('Cette session n\'est plus proposée à la réservation.');
    }

    private function requestFromSession(int $id, Request $request, DenormalizerInterface $denormalizer, ValidatorInterface $validator): ?ReservationRequest
    {
        $donnees = $request->getSession()->get(self::CLE_SESSION.$id);
        if (!\is_array($donnees)) {
            return null;
        }

        $demande = $denormalizer->denormalize($donnees, ReservationRequest::class);

        return 0 === \count($validator->validate($demande)) ? $demande : null;
    }
}
