<?php

namespace App\Reservation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ParticipantInput
{
    /** Liste de boutique-old (PROVISOIRE, QE-29). */
    public const SITUATIONS = ['Salarié', 'Gérant non salarié', 'Demandeur d\'emploi'];

    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 100)]
    public string $prenom = '';

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100)]
    public string $nom = '';

    #[Assert\NotBlank(message: 'La date de naissance est obligatoire.')]
    #[Assert\Date(message: 'Date de naissance invalide.')]
    public string $dateNaissance = '';

    #[Assert\NotBlank(message: 'Choisissez la situation du participant.')]
    #[Assert\Choice(choices: self::SITUATIONS, message: 'Situation inconnue.')]
    public string $situation = '';

    /** Passeport prévention : obligatoire pour une inscription en ligne (règle de boutique-old). */
    #[Assert\NotBlank(message: 'Le n° de sécurité sociale est obligatoire pour s\'inscrire en ligne. Sans ce numéro, contactez le service formation à contact@coprotec.net.')]
    public string $numeroSecu = '';

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        $numero = $this->normalizedSocialSecurityNumber();
        if ('' !== $numero && !Formats::isValidSocialSecurityNumber($numero)) {
            $context->buildViolation('N° de sécurité sociale invalide : 15 caractères (13 + clé), vérifiez la saisie.')
                ->atPath('numeroSecu')->addViolation();
        }

        $naissance = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->dateNaissance);
        if (false !== $naissance) {
            $age = $naissance->diff(new \DateTimeImmutable('today'))->y;
            if ($naissance > new \DateTimeImmutable('today') || $age < 14 || $age > 99) {
                $context->buildViolation('Date de naissance improbable, vérifiez la saisie.')->atPath('dateNaissance')->addViolation();
            }
        }
    }

    public function normalizedSocialSecurityNumber(): string
    {
        return strtoupper(str_replace([' ', '.', '-'], '', $this->numeroSecu));
    }
}
