<?php

namespace App\Reservation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Société qui inscrit ses salariés. Tous les champs de boutique-old étaient obligatoires, sauf la TVA
 * intracommunautaire. Réservation par des particuliers : QE-2.
 * Société hors de France : pas de SIRET, d'APE ni d'OPCO, champs masqués et ignorés (PROVISOIRE, QE-38).
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
    public string $codePostal = '';

    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(max: 120)]
    public string $ville = '';

    #[Assert\NotBlank(message: 'Choisissez le pays.')]
    #[Assert\Country(message: 'Pays inconnu.')]
    public string $pays = Formats::PAYS_DEFAUT;

    public string $siret = '';

    public string $ape = '';

    #[Assert\Length(max: 20)]
    public string $tvaIntracom = '';

    #[Assert\NotNull(message: 'Le nombre de salariés est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le nombre de salariés doit être positif.')]
    #[Assert\LessThan(1000000)]
    public ?int $nbSalaries = null;

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
    public string $telephone = '';

    #[Assert\NotBlank(message: 'L\'email de la société est obligatoire.')]
    #[Assert\Email(message: 'Adresse email invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ('' !== $this->codePostal && null !== $erreur = Formats::postalCodeError($this->normalizedPostalCode(), $this->pays)) {
            $context->buildViolation($erreur)->atPath('codePostal')->addViolation();
        }
        if ('' !== trim($this->telephone) && null === Formats::normalizePhone($this->telephone)) {
            $context->buildViolation(Formats::PHONE_ERROR)->atPath('telephone')->addViolation();
        }

        $tva = $this->normalizedVatNumber();
        if ($this->isFrench()) {
            $this->validateFrenchIdentifiers($context);
            if ('' !== $tva && 1 !== preg_match('/^FR[0-9A-Z]{2}\d{9}$/', $tva)) {
                $context->buildViolation('N° de TVA intracommunautaire invalide (ex. FR12345678901).')->atPath('tvaIntracom')->addViolation();
            }
        } elseif ('' !== $tva && 1 !== preg_match('/^[A-Z]{2}[0-9A-Z]{2,13}$/', $tva)) {
            $context->buildViolation('N° de TVA invalide : code pays puis numéro (ex. CHE123456789, DE123456789).')->atPath('tvaIntracom')->addViolation();
        }
    }

    private function validateFrenchIdentifiers(ExecutionContextInterface $context): void
    {
        $siret = $this->normalizedSiret();
        if ('' === $siret) {
            $context->buildViolation('Le SIRET est obligatoire.')->atPath('siret')->addViolation();
        } elseif (!Formats::isValidSiret($siret)) {
            $message = 1 === preg_match('/^\d{14}$/', $siret)
                ? 'SIRET invalide : la clé de contrôle ne correspond pas, vérifiez la saisie.'
                : \sprintf('SIRET invalide : 14 chiffres attendus (%d saisis).', \strlen(preg_replace('/\D/', '', $siret) ?? ''));
            $context->buildViolation($message)->atPath('siret')->addViolation();
        }

        if ('' === trim($this->ape)) {
            $context->buildViolation('Le code APE est obligatoire.')->atPath('ape')->addViolation();
        } elseif (1 !== preg_match('/^\d{4}[A-Z]$/', $this->normalizedApe())) {
            $context->buildViolation('Code APE invalide (ex. 4322B).')->atPath('ape')->addViolation();
        }

        if ('' === trim($this->opco)) {
            $context->buildViolation('L\'OPCO est obligatoire (ex. Constructys, AKTO).')->atPath('opco')->addViolation();
        }
    }

    public function isFrench(): bool
    {
        return Formats::PAYS_DEFAUT === $this->pays;
    }

    /** SIRET, APE et OPCO n'existent que pour une société française : vides ailleurs, même si saisis avant de changer de pays. */
    public function normalizedSiret(): string
    {
        return $this->isFrench() ? preg_replace('/[\s.]+/', '', $this->siret) ?? '' : '';
    }

    public function normalizedApe(): string
    {
        return $this->isFrench() ? strtoupper(str_replace(['.', ' '], '', trim($this->ape))) : '';
    }

    public function normalizedOpco(): string
    {
        return $this->isFrench() ? trim($this->opco) : '';
    }

    public function normalizedPostalCode(): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($this->codePostal)) ?? '');
    }

    /** « CHE-123.456.789 TVA » → « CHE123456789TVA ». */
    public function normalizedVatNumber(): string
    {
        return strtoupper(preg_replace('/[\s.\-]/', '', $this->tvaIntracom) ?? '');
    }
}
