<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Session de formation, copie locale de `session_formations` + remplissage de `sessions_ouvertes` SmartOF.
 */
#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: 'session_formation')]
#[ORM\Index(fields: ['dateDebut'])]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36, unique: true)]
    private string $smartofUid;

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private Course $formation;

    #[ORM\Column(length: 255)]
    private string $nom = '';

    #[ORM\Column]
    private \DateTimeImmutable $dateDebut;

    #[ORM\Column]
    private \DateTimeImmutable $dateFin;

    #[ORM\Column(length: 255)]
    private string $lieu = '';

    /** Nombre de places total (null = illimité, « ∞ » dans SmartOF). */
    #[ORM\Column(nullable: true)]
    private ?int $limitePlaces = null;

    /** Inscrits côté SmartOF lors de la dernière lecture de `sessions_ouvertes`. */
    #[ORM\Column]
    private int $inscritsSmartof = 0;

    /** Inscriptions ouvertes dans SmartOF (session présente dans `sessions_ouvertes`). */
    #[ORM\Column]
    private bool $ouverte = false;

    #[ORM\Column]
    private \DateTimeImmutable $synchroniseLe;

    public function __construct(string $smartofUid, Course $formation)
    {
        $this->smartofUid = $smartofUid;
        $this->formation = $formation;
        $this->dateDebut = $this->dateFin = $this->synchroniseLe = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSmartofUid(): string { return $this->smartofUid; }

    public function getFormation(): Course { return $this->formation; }
    public function setFormation(Course $formation): static { $this->formation = $formation; return $this; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getDateDebut(): \DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(\DateTimeImmutable $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }

    public function getDateFin(): \DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(\DateTimeImmutable $dateFin): static { $this->dateFin = $dateFin; return $this; }

    public function getLieu(): string { return $this->lieu; }
    public function setLieu(string $lieu): static { $this->lieu = $lieu; return $this; }

    public function getLimitePlaces(): ?int { return $this->limitePlaces; }
    public function setLimitePlaces(?int $limitePlaces): static { $this->limitePlaces = $limitePlaces; return $this; }

    public function getInscritsSmartof(): int { return $this->inscritsSmartof; }
    public function setInscritsSmartof(int $inscritsSmartof): static { $this->inscritsSmartof = $inscritsSmartof; return $this; }

    public function isOuverte(): bool { return $this->ouverte; }
    public function setOuverte(bool $ouverte): static { $this->ouverte = $ouverte; return $this; }

    public function getSynchroniseLe(): \DateTimeImmutable { return $this->synchroniseLe; }
    public function setSynchroniseLe(\DateTimeImmutable $synchroniseLe): static { $this->synchroniseLe = $synchroniseLe; return $this; }

    public function isMultiDay(): bool
    {
        return $this->dateDebut->format('Y-m-d') !== $this->dateFin->format('Y-m-d');
    }
}
