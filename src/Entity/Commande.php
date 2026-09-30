<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réservation d'une session pour 1 à N participants, avec un seul paiement.
 */
#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Index(fields: ['statut'])]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Référence envoyée à Monetico et reprise dans SmartOF (`customId` du commanditaire). */
    #[ORM\Column(length: 20, unique: true)]
    private string $numero;

    /** Jeton d'accès à la page de confirmation (pas de compte client). */
    #[ORM\Column(length: 32, unique: true)]
    private string $jeton;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Session $session;

    #[ORM\Column(enumType: StatutCommande::class)]
    private StatutCommande $statut = StatutCommande::PaiementEnCours;

    #[ORM\Column(enumType: Financement::class)]
    private Financement $financement;

    #[ORM\Column(enumType: ModePaiement::class)]
    private ModePaiement $modePaiement;

    #[ORM\Column(length: 12)]
    private string $identifiantFranceTravail = '';

    #[ORM\Embedded(class: Contact::class)]
    private Contact $contact;

    #[ORM\Embedded(class: Societe::class)]
    private Societe $societe;

    #[ORM\Column(type: Types::TEXT)]
    private string $remarque = '';

    /** @var Collection<int, Participant> */
    #[ORM\OneToMany(targetEntity: Participant::class, mappedBy: 'commande', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $participants;

    #[ORM\Column]
    private int $nbParticipants;

    #[ORM\Column]
    private int $prixUnitaireHtCentimes;

    #[ORM\Column]
    private float $tauxTva;

    #[ORM\Column]
    private int $totalHtCentimes;

    #[ORM\Column]
    private int $totalTtcCentimes;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    /** Fin du blocage des places pendant le paiement CB. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expireLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $valideeLe = null;

    #[ORM\Column]
    private \DateTimeImmutable $cgvAccepteesLe;

    /** Retour Monetico (code-retour, autorisation, carte masquée…), sans donnée sensible. */
    #[ORM\Column(type: Types::JSON)]
    private array $paiementDetails = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $transmiseLe = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $smartofCommanditaireUid = null;

    #[ORM\Column]
    private int $tentativesTransmission = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $prochaineTentativeLe = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $derniereErreur = '';

    public function __construct(string $numero, string $jeton, Session $session, Financement $financement, ModePaiement $modePaiement)
    {
        $this->numero = $numero;
        $this->jeton = $jeton;
        $this->session = $session;
        $this->financement = $financement;
        $this->modePaiement = $modePaiement;
        $this->contact = new Contact();
        $this->societe = new Societe();
        $this->participants = new ArrayCollection();
        $this->creeLe = $this->cgvAccepteesLe = new \DateTimeImmutable();

        $formation = $session->getFormation();
        $this->prixUnitaireHtCentimes = (int) $formation->getPrixHtCentimes();
        $this->tauxTva = $formation->getTauxTva();
        $this->nbParticipants = 0;
        $this->totalHtCentimes = $this->totalTtcCentimes = 0;
    }

    public function ajouterParticipant(Participant $participant): void
    {
        $participant->setCommande($this);
        $this->participants->add($participant);
        $this->nbParticipants = $this->participants->count();
        $this->totalHtCentimes = $this->prixUnitaireHtCentimes * $this->nbParticipants;
        $this->totalTtcCentimes = (int) round($this->totalHtCentimes * (1 + $this->tauxTva / 100));
    }

    public function valider(\DateTimeImmutable $maintenant, array $paiementDetails = []): void
    {
        $this->statut = StatutCommande::Validee;
        $this->valideeLe = $maintenant;
        $this->expireLe = null;
        $this->paiementDetails = $paiementDetails;
        $this->prochaineTentativeLe = $maintenant;
    }

    public function refuserPaiement(array $paiementDetails): void
    {
        $this->statut = StatutCommande::PaiementRefuse;
        $this->paiementDetails = $paiementDetails;
    }

    public function expirer(): void
    {
        $this->statut = StatutCommande::Expiree;
    }

    public function marquerTransmise(string $commanditaireUid, \DateTimeImmutable $maintenant): void
    {
        $this->smartofCommanditaireUid = $commanditaireUid;
        $this->transmiseLe = $maintenant;
        $this->prochaineTentativeLe = null;
        $this->derniereErreur = '';
    }

    public function echecTransmission(string $erreur, ?\DateTimeImmutable $prochaineTentative): void
    {
        ++$this->tentativesTransmission;
        $this->derniereErreur = $erreur;
        $this->prochaineTentativeLe = $prochaineTentative;
    }

    public function getId(): ?int { return $this->id; }
    public function getNumero(): string { return $this->numero; }
    public function getJeton(): string { return $this->jeton; }
    public function getSession(): Session { return $this->session; }
    public function getStatut(): StatutCommande { return $this->statut; }
    public function getFinancement(): Financement { return $this->financement; }
    public function getModePaiement(): ModePaiement { return $this->modePaiement; }

    public function getIdentifiantFranceTravail(): string { return $this->identifiantFranceTravail; }
    public function setIdentifiantFranceTravail(string $identifiant): static { $this->identifiantFranceTravail = $identifiant; return $this; }

    public function getContact(): Contact { return $this->contact; }
    public function getSociete(): Societe { return $this->societe; }

    public function getRemarque(): string { return $this->remarque; }
    public function setRemarque(string $remarque): static { $this->remarque = $remarque; return $this; }

    /** @return Collection<int, Participant> */
    public function getParticipants(): Collection { return $this->participants; }
    public function getNbParticipants(): int { return $this->nbParticipants; }
    public function getPrixUnitaireHtCentimes(): int { return $this->prixUnitaireHtCentimes; }
    public function getTauxTva(): float { return $this->tauxTva; }
    public function getTotalHtCentimes(): int { return $this->totalHtCentimes; }
    public function getTotalTtcCentimes(): int { return $this->totalTtcCentimes; }
    public function getCreeLe(): \DateTimeImmutable { return $this->creeLe; }

    public function getExpireLe(): ?\DateTimeImmutable { return $this->expireLe; }
    public function setExpireLe(?\DateTimeImmutable $expireLe): static { $this->expireLe = $expireLe; return $this; }

    public function getValideeLe(): ?\DateTimeImmutable { return $this->valideeLe; }
    public function getCgvAccepteesLe(): \DateTimeImmutable { return $this->cgvAccepteesLe; }
    public function getPaiementDetails(): array { return $this->paiementDetails; }
    public function getTransmiseLe(): ?\DateTimeImmutable { return $this->transmiseLe; }
    public function getSmartofCommanditaireUid(): ?string { return $this->smartofCommanditaireUid; }
    public function getTentativesTransmission(): int { return $this->tentativesTransmission; }
    public function getProchaineTentativeLe(): ?\DateTimeImmutable { return $this->prochaineTentativeLe; }
    public function getDerniereErreur(): string { return $this->derniereErreur; }
}
