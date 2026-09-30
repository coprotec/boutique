<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Participant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Commande $commande;

    #[ORM\Column(length: 100)]
    private string $prenom;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dateNaissance;

    #[ORM\Column(length: 50)]
    private string $situation;

    /** N° de sécurité sociale chiffré (libsodium, cf. Chiffreur) ; vidé après transmission et délai RGPD (QE-26). */
    #[ORM\Column(type: Types::TEXT)]
    private string $numeroSecuChiffre = '';

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $smartofApprenantUid = null;

    public function __construct(string $prenom, string $nom, \DateTimeImmutable $dateNaissance, string $situation, string $numeroSecuChiffre)
    {
        $this->prenom = $prenom;
        $this->nom = $nom;
        $this->dateNaissance = $dateNaissance;
        $this->situation = $situation;
        $this->numeroSecuChiffre = $numeroSecuChiffre;
    }

    public function getId(): ?int { return $this->id; }
    public function getCommande(): Commande { return $this->commande; }
    public function setCommande(Commande $commande): void { $this->commande = $commande; }
    public function getPrenom(): string { return $this->prenom; }
    public function getNom(): string { return $this->nom; }
    public function getDateNaissance(): \DateTimeImmutable { return $this->dateNaissance; }
    public function getSituation(): string { return $this->situation; }
    public function getNumeroSecuChiffre(): string { return $this->numeroSecuChiffre; }
    public function effacerNumeroSecu(): void { $this->numeroSecuChiffre = ''; }
    public function getSmartofApprenantUid(): ?string { return $this->smartofApprenantUid; }
    public function setSmartofApprenantUid(?string $uid): void { $this->smartofApprenantUid = $uid; }
}
