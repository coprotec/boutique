<?php

namespace App\Reservation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ContactInput
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
    public string $telephone = '';

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ('' !== trim($this->telephone) && null === Formats::normalizePhone($this->telephone)) {
            $context->buildViolation(Formats::PHONE_ERROR)->atPath('telephone')->addViolation();
        }
    }
}
