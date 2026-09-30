<?php

namespace App\Entity;

use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Produit de formation COPROTEC, copie locale de `produit_formations` SmartOF (cf. CatalogueSynchronizer).
 */
#[ORM\Entity(repositoryClass: FormationRepository::class)]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36, unique: true)]
    private string $smartofUid;

    /** Référence SmartOF (`customId`), ex. T68-25. */
    #[ORM\Column(length: 100)]
    private string $reference = '';

    /** Adresse stable de la fiche, utilisée par les liens du site vitrine. */
    #[ORM\Column(length: 120, unique: true)]
    private string $slug;

    #[ORM\Column(length: 255)]
    private string $intitule = '';

    #[ORM\Column(length: 100)]
    private string $dureeAffichee = '';

    #[ORM\Column(nullable: true)]
    private ?float $dureeHeures = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $objectifs = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $prerequis = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $publicVise = '';

    /** Prix HT par participant, en centimes (null = pas de tarif dans SmartOF, non réservable). */
    #[ORM\Column(nullable: true)]
    private ?int $prixHtCentimes = null;

    /** Taux de TVA en points (20 = 20 %). */
    #[ORM\Column]
    private float $tauxTva = 20.0;

    #[ORM\Column]
    private bool $eligibleCpf = false;

    /** Faux quand le produit n'est plus renvoyé par la synchro (archivé, retiré de la boutique). */
    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $synchroniseLe;

    /** @var Collection<int, Session> */
    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'formation')]
    #[ORM\OrderBy(['dateDebut' => 'ASC'])]
    private Collection $sessions;

    public function __construct(string $smartofUid)
    {
        $this->smartofUid = $smartofUid;
        $this->slug = $smartofUid;
        $this->sessions = new ArrayCollection();
        $this->synchroniseLe = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSmartofUid(): string { return $this->smartofUid; }

    public function getReference(): string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = $reference; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getIntitule(): string { return $this->intitule; }
    public function setIntitule(string $intitule): static { $this->intitule = $intitule; return $this; }

    public function getDureeAffichee(): string { return $this->dureeAffichee; }
    public function setDureeAffichee(string $dureeAffichee): static { $this->dureeAffichee = $dureeAffichee; return $this; }

    public function getDureeHeures(): ?float { return $this->dureeHeures; }
    public function setDureeHeures(?float $dureeHeures): static { $this->dureeHeures = $dureeHeures; return $this; }

    public function getObjectifs(): string { return $this->objectifs; }
    public function setObjectifs(string $objectifs): static { $this->objectifs = $objectifs; return $this; }

    public function getPrerequis(): string { return $this->prerequis; }
    public function setPrerequis(string $prerequis): static { $this->prerequis = $prerequis; return $this; }

    public function getPublicVise(): string { return $this->publicVise; }
    public function setPublicVise(string $publicVise): static { $this->publicVise = $publicVise; return $this; }

    public function getPrixHtCentimes(): ?int { return $this->prixHtCentimes; }
    public function setPrixHtCentimes(?int $prixHtCentimes): static { $this->prixHtCentimes = $prixHtCentimes; return $this; }

    public function getTauxTva(): float { return $this->tauxTva; }
    public function setTauxTva(float $tauxTva): static { $this->tauxTva = $tauxTva; return $this; }

    public function getPrixTtcCentimes(): ?int
    {
        return null === $this->prixHtCentimes ? null : (int) round($this->prixHtCentimes * (1 + $this->tauxTva / 100));
    }

    public function isEligibleCpf(): bool { return $this->eligibleCpf; }
    public function setEligibleCpf(bool $eligibleCpf): static { $this->eligibleCpf = $eligibleCpf; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getSynchroniseLe(): \DateTimeImmutable { return $this->synchroniseLe; }
    public function setSynchroniseLe(\DateTimeImmutable $synchroniseLe): static { $this->synchroniseLe = $synchroniseLe; return $this; }

    /** @return Collection<int, Session> */
    public function getSessions(): Collection { return $this->sessions; }
}
