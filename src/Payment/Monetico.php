<?php

namespace App\Payment;

use App\Entity\Order;
use App\Reservation\Formats;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Paiement CB Monetico, formulaire v3.0 signé HMAC-SHA1 (repris de boutique-old, view/reservation/etape3.inc.php).
 *
 * Le paiement n'est validé qu'à réception de la notification serveur à serveur (« interface de retour », URL à
 * déclarer dans l'espace Monetico), dont on vérifie la signature. Le retour navigateur ne sert qu'à l'affichage.
 */
class Monetico
{
    private const URL_PROD = 'https://p.monetico-services.com/paiement.cgi';
    private const URL_TEST = 'https://p.monetico-services.com/test/paiement.cgi';

    public function __construct(
        #[Autowire('%env(MONETICO_TPE)%')] private readonly string $tpe,
        #[Autowire('%env(MONETICO_CLE)%')] private readonly string $cleHex,
        #[Autowire('%env(MONETICO_SOCIETE)%')] private readonly string $societe,
        #[Autowire('%env(bool:MONETICO_TEST)%')] private readonly bool $test,
        /** Sans identifiants (dev), le paiement passe par un simulateur local (PaymentController::simulateur). */
        #[Autowire('%env(bool:MONETICO_SIMULE)%')] private readonly bool $simule,
    ) {
    }

    public function isSimulated(): bool
    {
        return $this->simule;
    }

    public function paymentUrl(): string
    {
        return $this->test ? self::URL_TEST : self::URL_PROD;
    }

    /** @return array<string, string> champs du formulaire POST vers Monetico, MAC compris */
    public function formFields(Order $commande, string $urlRetourOk, string $urlRetourErreur): array
    {
        $contact = $commande->getContact();
        $societe = $commande->getSociete();
        $adresse = [
            'firstName' => $contact->prenom,
            'lastName' => $contact->nom,
            'addressLine1' => $societe->adresse,
            'city' => $societe->ville,
            'postalCode' => $societe->codePostal,
            'country' => $societe->pays,
        ];
        // Contexte de commande exigé par Monetico v3 (3-D Secure 2), même contenu que boutique-old.
        $contexte = [
            'billing' => $adresse,
            'shipping' => $adresse + ['email' => $societe->email, 'phone' => $this->internationalPhone($societe->telephone)],
            'client' => ['email' => $societe->email, 'phone' => $this->internationalPhone($societe->telephone)],
        ];

        $champs = [
            'TPE' => $this->tpe,
            'contexte_commande' => base64_encode((string) json_encode($contexte, \JSON_FORCE_OBJECT | \JSON_UNESCAPED_UNICODE)),
            'date' => (new \DateTimeImmutable())->format('d/m/Y:H:i:s'),
            'lgue' => 'FR',
            'mail' => $contact->email,
            'montant' => number_format($commande->getTotalTtcCentimes() / 100, 2, '.', '').'EUR',
            'reference' => $commande->getNumero(),
            'societe' => $this->societe,
            'url_retour_err' => $urlRetourErreur,
            'url_retour_ok' => $urlRetourOk,
            'version' => '3.0',
        ];
        $champs['MAC'] = $this->mac($champs);

        return $champs;
    }

    /** @param array<string, string> $champs champs reçus (notification), MAC compris */
    public function isSignatureValid(array $champs): bool
    {
        $recu = strtoupper((string) ($champs['MAC'] ?? ''));
        unset($champs['MAC']);

        return '' !== $recu && hash_equals($this->mac($champs), $recu);
    }

    /** Réponse attendue par Monetico à la notification : cdr=0 reçue et traitée, cdr=1 signature invalide. */
    public function acknowledgement(bool $ok): string
    {
        return "version=2\ncdr=".($ok ? '0' : '1')."\n";
    }

    /**
     * MAC v3 : HMAC-SHA1 de « cle=valeur » triés par clé (ordre ASCII) et joints par « * », avec la clé binaire.
     *
     * @param array<string, string> $champs
     */
    public function mac(array $champs): string
    {
        ksort($champs, \SORT_STRING);
        $chaine = implode('*', array_map(static fn (string $k, string $v): string => "$k=$v", array_keys($champs), $champs));

        return strtoupper(hash_hmac('sha1', $chaine, (string) hex2bin($this->cleHex)));
    }

    /** Format Monetico : indicatif-numéro national, ex. +33-369288900, +41-763330111. */
    private function internationalPhone(string $telephone): string
    {
        [$indicatif, $national] = Formats::splitPhone($telephone);

        return "+$indicatif-$national";
    }
}
