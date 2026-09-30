<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Société qui inscrit ses salariés (champs de boutique-old). */
#[ORM\Embeddable]
class Societe
{
    #[ORM\Column(length: 255)]
    public string $raisonSociale = '';

    #[ORM\Column(length: 255)]
    public string $adresse = '';

    #[ORM\Column(length: 5)]
    public string $codePostal = '';

    #[ORM\Column(length: 120)]
    public string $ville = '';

    #[ORM\Column(length: 14)]
    public string $siret = '';

    #[ORM\Column(length: 6)]
    public string $ape = '';

    #[ORM\Column(length: 20)]
    public string $tvaIntracom = '';

    #[ORM\Column]
    public int $nbSalaries = 0;

    #[ORM\Column(length: 100)]
    public string $opco = '';

    #[ORM\Column(length: 50)]
    public string $organisationProfessionnelle = '';

    #[ORM\Column(length: 100)]
    public string $dirigeantPrenom = '';

    #[ORM\Column(length: 100)]
    public string $dirigeantNom = '';

    #[ORM\Column(length: 30)]
    public string $telephone = '';

    #[ORM\Column(length: 180)]
    public string $email = '';
}
