<?php

namespace App\Notification;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Préprod : tous les emails partent vers les seules adresses de MAILER_OVERSEER (liste séparée par des virgules),
 * jamais vers le client ni le service. L'objet est préfixé par les destinataires prévus, pour voir qui l'aurait reçu.
 * Variable vide (prod) : aucun effet.
 */
#[AsEventListener]
final class MailRedirectSubscriber
{
    /** @var list<Address> */
    private readonly array $surveillants;

    public function __construct(#[Autowire('%env(MAILER_OVERSEER)%')] string $surveillants)
    {
        $this->surveillants = array_map(
            static fn (string $adresse): Address => new Address($adresse),
            array_values(array_filter(array_map('trim', explode(',', $surveillants)))),
        );
    }

    public function __invoke(MessageEvent $event): void
    {
        if ([] === $this->surveillants) {
            return;
        }

        $message = $event->getMessage();
        if ($message instanceof Email) {
            $prevus = implode(', ', array_map(static fn (Address $a): string => $a->getAddress(), [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()]));
            $message->subject(\sprintf('[PRÉPROD → %s] %s', $prevus, $message->getSubject()));
        }
        $event->getEnvelope()->setRecipients($this->surveillants);
    }
}
