<?php

namespace App\Paiement;

use App\Entity\StatutCommande;
use App\Notification\Notificateur;
use App\Repository\CommandeRepository;
use App\Smartof\Transmetteur;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Applique la notification serveur à serveur de Monetico à la commande concernée.
 */
class ValidationPaiement
{
    /** Champs du retour Monetico conservés sur la commande (aucune donnée de carte complète). */
    private const DETAILS = ['code-retour', 'date', 'montant', 'numauto', 'motifrefus', 'brand', 'status3ds', 'modepaiement', 'originecb'];

    public function __construct(
        private readonly Monetico $monetico,
        private readonly CommandeRepository $commandes,
        private readonly EntityManagerInterface $em,
        private readonly Notificateur $notificateur,
        private readonly Transmetteur $transmetteur,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, string> $champs
     *
     * @return bool signature valide (accusé cdr=0), faux sinon (cdr=1)
     */
    public function traiterNotification(array $champs): bool
    {
        if (!$this->monetico->signatureValide($champs)) {
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
        if (StatutCommande::Validee === $commande->getStatut()) {
            return true; // Notification rejouée : déjà traitée.
        }

        $montantAttendu = number_format($commande->getTotalTtcCentimes() / 100, 2, '.', '').'EUR';
        if (\in_array($codeRetour, ['paiement', 'payetest'], true) && ($champs['montant'] ?? '') === $montantAttendu) {
            // Même après expiration du blocage : le client a payé, la place lui revient ; une éventuelle
            // surréservation est détectée à l'envoi vers SmartOF (QE-9).
            $commande->valider(new \DateTimeImmutable(), $details);
            $this->em->flush();
            $this->notificateur->commandeValidee($commande);
            $this->transmetteur->transmettre($commande);
        } else {
            $commande->refuserPaiement($details);
            $this->em->flush();
        }

        return true;
    }
}
