<?php

namespace App\Reservation;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactSaisi
{
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 100)]
    public string $prenom = '';

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100)]
    public string $nom = '';

    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Adresse email invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(message: 'Confirmez l\'adresse email.')]
    #[Assert\EqualTo(propertyPath: 'email', message: 'Les deux adresses email ne correspondent pas.')]
    public string $emailConfirmation = '';

    #[Assert\NotBlank(message: 'Le téléphone est obligatoire.')]
    #[Assert\Regex(pattern: Formats::TELEPHONE, message: 'Numéro de téléphone invalide (ex. 03 69 28 89 00 ou +33 3 69 28 89 00).')]
    public string $telephone = '';
}
