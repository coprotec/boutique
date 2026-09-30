<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Personne qui passe la commande. */
#[ORM\Embeddable]
class Contact
{
    #[ORM\Column(length: 100)]
    public string $prenom = '';

    #[ORM\Column(length: 100)]
    public string $nom = '';

    #[ORM\Column(length: 180)]
    public string $email = '';

    #[ORM\Column(length: 30)]
    public string $telephone = '';
}
