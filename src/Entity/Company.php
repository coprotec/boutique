<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Société qui inscrit ses salariés (champs de boutique-old). */
#[ORM\Embeddable]
class Company
{
    #[ORM\Column(length: 255)]
    public string $raisonSociale = '';

    #[ORM\Column(length: 255)]
    public string $adresse = '';

    /** 10 caractères : codes postaux étrangers (Royaume-Uni « SW1A 1AA », Pays-Bas « 1234 AB »…). */
    #[ORM\Column(length: 10)]
    public string $codePostal = '';

    #[ORM\Column(length: 120)]
    public string $ville = '';

    /** Code pays ISO 3166-1 alpha-2. */
    #[ORM\Column(length: 2, options: ['fixed' => true, 'default' => 'FR'])]
    public string $pays = 'FR';

    /** Vide pour une société étrangère, comme l'APE et l'OPCO (QE-38). */
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

    /** Format international E.164 (+33369288900). */
    #[ORM\Column(length: 30)]
    public string $telephone = '';

    #[ORM\Column(length: 180)]
    public string $email = '';
}
