<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Payment\Monetico;
use App\Payment\PaymentValidator;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PaymentController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $commandes,
        private readonly Monetico $monetico,
    ) {
    }

    /** Redirection automatique vers la page de paiement Monetico (formulaire POST signé). */
    #[Route('/paiement/{jeton}', name: 'paiement_cb', methods: ['GET'], requirements: ['jeton' => '[a-f0-9]{32}'])]
    public function pay(string $jeton): Response
    {
        $commande = $this->findOrder($jeton);
        if (OrderStatus::PaiementEnCours !== $commande->getStatut() || $commande->getExpireLe() <= new \DateTimeImmutable()) {
            return $this->redirectToRoute('commande', ['jeton' => $jeton]);
        }

        $retour = $this->generateUrl('commande', ['jeton' => $jeton], UrlGeneratorInterface::ABSOLUTE_URL);
        $champs = $this->monetico->formFields($commande, $retour, $retour.'?paiement=erreur');

        return $this->render('paiement/redirection.html.twig', [
            'commande' => $commande,
            'action' => $this->monetico->isSimulated() ? $this->generateUrl('paiement_simulateur', ['jeton' => $jeton]) : $this->monetico->paymentUrl(),
            'champs' => $champs,
        ]);
    }

    /**
     * Notification serveur à serveur de Monetico (« interface de retour », URL à déclarer chez Monetico).
     * Accessible sans authentification même en préprod (cf. docker/apache-hote/).
     */
    #[Route('/paiement/monetico/notification', name: 'paiement_notification', methods: ['POST'], priority: 10)]
    public function notification(Request $request, PaymentValidator $validation): Response
    {
        $ok = $validation->handleNotification($request->request->all());

        return new Response($this->monetico->acknowledgement($ok), 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Simulateur local (MONETICO_SIMULE=1, dev uniquement) : remplace la page Monetico et envoie à la boutique
     * une notification signée comme le ferait Monetico, puis ramène le client sur l'URL de retour.
     */
    #[Route('/paiement/{jeton}/simulateur', name: 'paiement_simulateur', methods: ['POST'], requirements: ['jeton' => '[a-f0-9]{32}'])]
    public function simulator(string $jeton, Request $request, PaymentValidator $validation): Response
    {
        if (!$this->monetico->isSimulated()) {
            throw new NotFoundHttpException();
        }
        $commande = $this->findOrder($jeton);

        if (null === $request->request->get('resultat')) {
            return $this->render('paiement/simulateur.html.twig', ['commande' => $commande, 'champs' => $request->request->all()]);
        }

        $accepte = 'accepte' === $request->request->get('resultat');
        $notification = [
            'TPE' => (string) $request->request->get('TPE'),
            'date' => (string) $request->request->get('date'),
            'montant' => (string) $request->request->get('montant'),
            'reference' => $commande->getNumero(),
            'texte-libre' => '',
            'code-retour' => $accepte ? 'payetest' : 'Annulation',
            'numauto' => $accepte ? 'SIMU'.random_int(1000, 9999) : '',
            'motifrefus' => $accepte ? '' : 'Refus simulé',
            'brand' => 'VI',
        ];
        $notification['MAC'] = $this->monetico->mac($notification);
        $validation->handleNotification($notification);

        return $this->redirect((string) $request->request->get($accepte ? 'url_retour_ok' : 'url_retour_err'));
    }

    private function findOrder(string $jeton): Order
    {
        return $this->commandes->findOneByToken($jeton) ?? throw new NotFoundHttpException('Commande introuvable.');
    }
}
