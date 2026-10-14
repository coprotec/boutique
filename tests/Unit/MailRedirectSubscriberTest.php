<?php

namespace App\Tests\Unit;

use App\Notification\MailRedirectSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class MailRedirectSubscriberTest extends TestCase
{
    public function testRedirigeVersLesSurveillantsEtIndiqueLeDestinatairePrevu(): void
    {
        $evenement = $this->event('client@example.com', 'Confirmation');
        (new MailRedirectSubscriber(' qa@coprotec.net , compta@coprotec.net '))($evenement);

        self::assertSame(['qa@coprotec.net', 'compta@coprotec.net'], array_map(static fn (Address $a): string => $a->getAddress(), $evenement->getEnvelope()->getRecipients()));
        self::assertSame('[PRÉPROD → client@example.com] Confirmation', $evenement->getMessage()->getSubject());
    }

    public function testSansSurveillantRienNeChange(): void
    {
        $evenement = $this->event('client@example.com', 'Confirmation');
        (new MailRedirectSubscriber(''))($evenement);

        self::assertSame('client@example.com', $evenement->getEnvelope()->getRecipients()[0]->getAddress());
        self::assertSame('Confirmation', $evenement->getMessage()->getSubject());
    }

    private function event(string $destinataire, string $sujet): MessageEvent
    {
        $email = (new Email())->from('contact@coprotec.net')->to($destinataire)->subject($sujet)->text('…');

        return new MessageEvent($email, Envelope::create($email), 'null');
    }
}
