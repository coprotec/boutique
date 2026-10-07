<?php

namespace App\Reservation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Société qui inscrit ses salariés. Tous les champs de boutique-old étaient obligatoires, sauf la TVA
 * intracommunautaire. Réservation par des particuliers : QE-2.
 */
final class CompanyInput
{
    /** Liste de boutique-old (PROVISOIRE, QE-29). */
    public const ORGANISATIONS = ['Non-adhérent', 'CAPEB', 'FFB', 'SYNASAV'];

    #[Assert\NotBlank(message: 'La raison sociale est obligatoire.')]
    #[Assert\Length(max: 255)]
    public string $raisonSociale = '';

    #[Assert\NotBlank(message: 'L\'adresse est obligatoire.')]
    #[Assert\Length(max: 255)]
    public string $adresse = '';

    #[Assert\NotBlank(message: 'Le code postal est obligatoire.')]
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'Code postal invalide : 5 chiffres.')]
    public string $codePostal = '';

    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(max: 120)]
    public string $ville = '';

    #[Assert\NotBlank(message: 'Le SIRET est obligatoire.')]
    public string $siret = '';

    #[Assert\NotBlank(message: 'Le code APE est obligatoire.')]
    #[Assert\Regex(pattern: '/^\d{2}\.?\d{2}[A-Za-z]$/', message: 'Code APE invalide (ex. 4322B).')]
    public string $ape = '';

    #[Assert\Regex(pattern: '/^FR[0-9A-Z]{2}\d{9}$/', message: 'N° de TVA intracommunautaire invalide (ex. FR12345678901).')]
    public string $tvaIntracom = '';

    #[Assert\NotNull(message: 'Le nombre de salariés est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le nombre de salariés doit être positif.')]
    #[Assert\LessThan(1000000)]
    public ?int $nbSalaries = null;

    #[Assert\NotBlank(message: 'L\'OPCO est obligatoire (ex. Constructys, AKTO).')]
    #[Assert\Length(max: 100)]
    public string $opco = '';

    #[Assert\NotBlank(message: 'Choisissez votre organisation professionnelle.')]
    #[Assert\Choice(choices: self::ORGANISATIONS, message: 'Organisation professionnelle inconnue.')]
    public string $organisationProfessionnelle = '';

    #[Assert\NotBlank(message: 'Le prénom du dirigeant est obligatoire.')]
    #[Assert\Length(max: 100)]
    public string $dirigeantPrenom = '';

    #[Assert\NotBlank(message: 'Le nom du dirigeant est obligatoire.')]
    #[Assert\Length(max: 100)]
    public string $dirigeantNom = '';

    #[Assert\NotBlank(message: 'Le téléphone de la société est obligatoire.')]
    #[Assert\Regex(pattern: Formats::TELEPHONE, message: 'Numéro de téléphone invalide.')]
    public string $telephone = '';

    #[Assert\NotBlank(message: 'L\'email de la société est obligatoire.')]
    #[Assert\Email(message: 'Adresse email invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\Callback]
    public function validateSiret(ExecutionContextInterface $context): void
    {
        $siret = $this->normalizedSiret();
        if ('' === $siret) {
            return;
        }
        if (!Formats::isValidSiret($siret)) {
            $context->buildViolation('SIRET invalide : 14 chiffres, vérifiez la saisie.')->atPath('siret')->addViolation();
        }
    }

    public function normalizedSiret(): string
    {
        return preg_replace('/\s+/', '', $this->siret) ?? '';
    }

    public function normalizedApe(): string
    {
        return strtoupper(str_replace('.', '', $this->ape));
    }
}
