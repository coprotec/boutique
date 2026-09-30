<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\StatutCommande;
use App\Paiement\Monetico;
use App\Paiement\ValidationPaiement;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PaiementController extends AbstractController
{
    public function __construct(
        private readonly CommandeRepository $commandes,
        private readonly Monetico $monetico,
    ) {
    }

    /** Redirection automatique vers la page de paiement Monetico (formulaire POST signé). */
    #[Route('/paiement/{jeton}', name: 'paiement_cb', methods: ['GET'], requirements: ['jeton' => '[a-f0-9]{32}'])]
    public function payer(string $jeton): Response
    {
        $commande = $this->commande($jeton);
        if (StatutCommande::PaiementEnCours !== $commande->getStatut() || $commande->getExpireLe() <= new \DateTimeImmutable()) {
            return $this->redirectToRoute('commande', ['jeton' => $jeton]);
        }

        $retour = $this->generateUrl('commande', ['jeton' => $jeton], UrlGeneratorInterface::ABSOLUTE_URL);
        $champs = $this->monetico->champsFormulaire($commande, $retour, $retour.'?paiement=erreur');

        return $this->render('paiement/redirection.html.twig', [
            'commande' => $commande,
            'action' => $this->monetico->estSimule() ? $this->generateUrl('paiement_simulateur', ['jeton' => $jeton]) : $this->monetico->urlPaiement(),
            'champs' => $champs,
        ]);
    }

    /**
     * Notification serveur à serveur de Monetico (« interface de retour », URL à déclarer chez Monetico).
     * Accessible sans authentification même en préprod (cf. vhost Nginx).
     */
    #[Route('/paiement/monetico/notification', name: 'paiement_notification', methods: ['POST'], priority: 10)]
    public function notification(Request $request, ValidationPaiement $validation): Response
    {
        $ok = $validation->traiterNotification($request->request->all());

        return new Response($this->monetico->accuse($ok), 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Simulateur local (MONETICO_SIMULE=1, dev uniquement) : remplace la page Monetico et envoie à la boutique
     * une notification signée comme le ferait Monetico, puis ramène le client sur l'URL de retour.
     */
    #[Route('/paiement/{jeton}/simulateur', name: 'paiement_simulateur', methods: ['POST'], requirements: ['jeton' => '[a-f0-9]{32}'])]
    public function simulateur(string $jeton, Request $request, ValidationPaiement $validation): Response
    {
        if (!$this->monetico->estSimule()) {
            throw new NotFoundHttpException();
        }
        $commande = $this->commande($jeton);

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
        $validation->traiterNotification($notification);

        return $this->redirect((string) $request->request->get($accepte ? 'url_retour_ok' : 'url_retour_err'));
    }

    private function commande(string $jeton): Commande
    {
        return $this->commandes->findOneByJeton($jeton) ?? throw new NotFoundHttpException('Commande introuvable.');
    }
}
