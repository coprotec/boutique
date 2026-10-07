<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page de suivi d'une commande (retour de paiement, lien de l'email). Accès par jeton, pas de compte client.
 */
final class OrderController extends AbstractController
{
    #[Route('/commande/{jeton}', name: 'commande', methods: ['GET'], requirements: ['jeton' => '[a-f0-9]{32}'])]
    public function show(
        string $jeton,
        OrderRepository $commandes,
        #[Autowire('%env(BOUTIQUE_IBAN)%')] string $iban,
    ): Response {
        $commande = $commandes->findOneByToken($jeton) ?? throw new NotFoundHttpException('Commande introuvable.');

        $response = $this->render('commande/afficher.html.twig', ['commande' => $commande, 'iban' => $iban]);
        // Page personnelle : jamais en cache partagé, jamais indexée.
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->setPrivate();

        return $response;
    }
}
