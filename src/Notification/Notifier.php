<?php

namespace App\Notification;

use App\Entity\Order;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Emails de la boutique (envoi via Brevo en préprod / prod, Mailpit en dev).
 * Répartition avec les envois automatiques de SmartOF : QE-19 à QE-22.
 */
class Notifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(BOUTIQUE_EMAIL_EXPEDITEUR)%')] private readonly string $expediteur,
        // PROVISOIRE (QE-22) : adresse prévenue à chaque nouvelle réservation.
        #[Autowire('%env(BOUTIQUE_EMAIL_SERVICE)%')] private readonly string $emailService,
        #[Autowire('%env(BOUTIQUE_EMAIL_ALERTES)%')] private readonly string $emailAlertes,
        #[Autowire('%env(BOUTIQUE_IBAN)%')] private readonly string $iban,
    ) {
    }

    /** Récapitulatif au client (avec les instructions de paiement hors CB) et copie au service formation. */
    public function orderValidated(Order $commande): void
    {
        $contexte = [
            'commande' => $commande,
            'url' => $this->urls->generate('commande', ['jeton' => $commande->getJeton()], UrlGeneratorInterface::ABSOLUTE_URL),
            'iban' => $this->iban,
        ];

        $this->send((new TemplatedEmail())
            ->to(new Address($commande->getContact()->email, $commande->getContact()->prenom.' '.$commande->getContact()->nom))
            ->subject(\sprintf('Votre réservation %s — %s', $commande->getNumero(), $commande->getSession()->getFormation()->getIntitule()))
            ->htmlTemplate('emails/commande_client.html.twig')
            ->context($contexte), $commande);

        if ('' !== $this->emailService) {
            $this->send((new TemplatedEmail())
                ->to($this->emailService)
                ->subject(\sprintf('[Boutique] Nouvelle réservation %s — %d participant(s)', $commande->getNumero(), $commande->getNbParticipants()))
                ->htmlTemplate('emails/commande_service.html.twig')
                ->context($contexte), $commande);
        }
    }

    public function sendFailureAlert(Order $commande, bool $surreservation): void
    {
        if ('' === $this->emailAlertes) {
            $this->logger->critical('Alerte transmission SmartOF (BOUTIQUE_EMAIL_ALERTES vide, aucun email envoyé)', ['commande' => $commande->getNumero(), 'erreur' => $commande->getDerniereErreur()]);

            return;
        }

        $this->send((new TemplatedEmail())
            ->to($this->emailAlertes)
            ->priority(TemplatedEmail::PRIORITY_HIGH)
            ->subject(\sprintf('[Boutique] %s : commande %s non transmise à SmartOF', $surreservation ? 'Surréservation' : 'Action requise', $commande->getNumero()))
            ->htmlTemplate('emails/alerte_transmission.html.twig')
            ->context(['commande' => $commande, 'surreservation' => $surreservation]), $commande);
    }

    private function send(TemplatedEmail $email, Order $commande): void
    {
        $email->from(new Address($this->expediteur, 'COPROTEC Formations'));
        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Un email perdu ne doit pas faire échouer la réservation : on le journalise pour relance manuelle.
            $this->logger->error('Email non envoyé', ['commande' => $commande->getNumero(), 'sujet' => $email->getSubject(), 'erreur' => $e->getMessage()]);
        }
    }
}
