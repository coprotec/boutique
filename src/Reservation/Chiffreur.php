<?php

namespace App\Reservation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Chiffrement applicatif des données sensibles (n° de sécurité sociale), libsodium secretbox.
 * Clé : BOUTIQUE_CLE_CHIFFREMENT, 32 octets encodés en base64 (`php -r "echo base64_encode(random_bytes(32));"`).
 */
class Chiffreur
{
    private string $cle;

    public function __construct(#[Autowire('%env(BOUTIQUE_CLE_CHIFFREMENT)%')] string $cleBase64)
    {
        $cle = base64_decode($cleBase64, true);
        if (false === $cle || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($cle)) {
            throw new \InvalidArgumentException('BOUTIQUE_CLE_CHIFFREMENT doit contenir 32 octets encodés en base64.');
        }
        $this->cle = $cle;
    }

    public function chiffrer(string $clair): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($clair, $nonce, $this->cle));
    }

    public function dechiffrer(string $chiffre): string
    {
        $donnees = base64_decode($chiffre, true);
        $clair = false === $donnees ? false : sodium_crypto_secretbox_open(
            substr($donnees, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($donnees, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->cle,
        );
        if (false === $clair) {
            throw new \RuntimeException('Donnée chiffrée illisible (clé BOUTIQUE_CLE_CHIFFREMENT changée ?).');
        }

        return $clair;
    }
}
