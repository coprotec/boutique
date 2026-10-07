<?php

namespace App\Payment;

use App\Entity\OrderStatus;
use App\Notification\Notifier;
use App\Repository\OrderRepository;
use App\Smartof\EnrollmentSender;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Applique la notification serveur à serveur de Monetico à la commande concernée.
 */
class PaymentValidator
{
    /** Champs du retour Monetico conservés sur la commande (aucune donnée de carte complète). */
    private const DETAILS = ['code-retour', 'date', 'montant', 'numauto', 'motifrefus', 'brand', 'status3ds', 'modepaiement', 'originecb'];

    public function __construct(
        private readonly Monetico $monetico,
        private readonly OrderRepository $commandes,
        private readonly EntityManagerInterface $em,
        private readonly Notifier $notificateur,
        private readonly EnrollmentSender $transmetteur,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, string> $champs
     *
     * @return bool signature valide (accusé cdr=0), faux sinon (cdr=1)
     */
    public function handleNotification(array $champs): bool
    {
        if (!$this->monetico->isSignatureValid($champs)) {
            $this->logger->warning('Notification Monetico à la signature invalide', ['reference' => $champs['reference'] ?? null]);

            return false;
        }

        $commande = $this->commandes->findOneBy(['numero' => $champs['reference'] ?? '']);
        $codeRetour = $champs['code-retour'] ?? '';
        $details = array_intersect_key($champs, array_flip(self::DETAILS));
        $this->logger->info('Notification Monetico', ['reference' => $champs['reference'] ?? null, 'code-retour' => $codeRetour]);

        if (null === $commande) {
            $this->logger->error('Notification Monetico pour une commande inconnue', $details);

            return true;
        }
        if (OrderStatus::Validee === $commande->getStatut()) {
            return true; // Notification rejouée : déjà traitée.
        }

        $montantAttendu = number_format($commande->getTotalTtcCentimes() / 100, 2, '.', '').'EUR';
        if (\in_array($codeRetour, ['paiement', 'payetest'], true) && ($champs['montant'] ?? '') === $montantAttendu) {
            // Même après expiration du blocage : le client a payé, la place lui revient ; une éventuelle
            // surréservation est détectée à l'envoi vers SmartOF ; le client est remboursé par virement (QE-9).
            $commande->validate(new \DateTimeImmutable(), $details);
            $this->em->flush();
            $this->notificateur->orderValidated($commande);
            $this->transmetteur->send($commande);
        } else {
            $commande->refusePayment($details);
            $this->em->flush();
        }

        return true;
    }
}
