<?php

namespace App\Catalog;

use App\Entity\Course;
use App\Entity\Session;
use App\Repository\CourseRepository;
use App\Repository\SessionRepository;
use App\Smartof\SmartofClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Copie locale du catalogue SmartOF : formations COPROTEC, leurs sessions à venir et leur remplissage.
 *
 * Synchro complète à chaque passage (catalogue de petite taille) : ce qui n'est plus renvoyé par SmartOF
 * est désactivé (formation) ou fermé (session), jamais supprimé, pour garder le lien avec les commandes.
 */
class CatalogSynchronizer
{
    /** Taille des lots d'UID produits dans filter[…][any], pour garder des URL raisonnables. */
    private const LOT_PRODUITS = 50;

    public function __construct(
        private readonly SmartofClient $smartof,
        private readonly EntityManagerInterface $em,
        private readonly CourseRepository $formations,
        private readonly SessionRepository $sessions,
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
        // PROVISOIRE (QE-4 / QSO-3) : champ SmartOF qui désigne les formations vendues sur la boutique.
        #[Autowire('%env(SMARTOF_FILTRE_BOUTIQUE)%')] private readonly string $filtreBoutique,
        // PROVISOIRE (QE-28) : champ SmartOF qui désigne les formations éligibles au CPF.
        #[Autowire('%env(SMARTOF_FILTRE_CPF)%')] private readonly string $filtreCpf,
    ) {
    }

    public function synchronize(): SyncReport
    {
        $maintenant = new \DateTimeImmutable();
        $rapport = new SyncReport();

        $formations = $this->syncCourses($maintenant, $rapport);
        $this->syncSessions($formations, $maintenant, $rapport);
        $this->em->flush();

        $this->logger->info('Synchro catalogue SmartOF terminée', $rapport->toArray());

        return $rapport;
    }

    /** @return array<string, Course> formations actives indexées par UID SmartOF */
    private function syncCourses(\DateTimeImmutable $maintenant, SyncReport $rapport): array
    {
        [$champ, $valeur] = $this->parseFilter($this->filtreBoutique);
        $existantes = $this->formations->findAllIndexedBySmartofUid();
        $actives = [];

        foreach ($this->smartof->produitFormations([$champ => ['eq' => $valeur], 'archived' => ['eq' => 'false']]) as $produit) {
            $uid = $produit['produitFormationUid'];
            $formation = $existantes[$uid] ?? new Course($uid);
            $this->hydrateCourse($formation, $produit, $maintenant);
            $this->em->persist($formation);
            $actives[$uid] = $formation;
        }

        foreach ($existantes as $uid => $formation) {
            if (!isset($actives[$uid]) && $formation->isActive()) {
                $formation->setActive(false);
                ++$rapport->formationsRetirees;
            }
        }

        $rapport->formations = \count($actives);
        $this->assignSlugs($actives, $existantes);

        return $actives;
    }

    /** @param array<string, mixed> $produit */
    private function hydrateCourse(Course $formation, array $produit, \DateTimeImmutable $maintenant): void
    {
        $description = $produit['description'] ?? [];
        [$prixHt, $tva] = $this->price($produit['presetTarification']['tarifs'] ?? []);
        [$champCpf, $valeurCpf] = $this->parseFilter($this->filtreCpf);

        $formation
            ->setReference((string) ($produit['customId'] ?? ''))
            ->setIntitule((string) (($description['intituleDeLaFormation'] ?? '') ?: ($produit['meta']['nom'] ?? '')))
            ->setDureeAffichee((string) ($description['dureeDeLaFormationAffichee'] ?? ''))
            ->setDureeHeures(isset($description['dureeDeLaFormation']) ? (float) $description['dureeDeLaFormation'] : null)
            ->setObjectifs((string) ($description['objectifsDeLaFormation'] ?? ''))
            ->setPrerequis((string) ($description['preRequis'] ?? ''))
            ->setPublicVise((string) ($description['publicVise'] ?? ''))
            ->setPrixHtCentimes($prixHt)
            ->setTauxTva($tva)
            ->setEligibleCpf($valeurCpf === $this->value($produit, $champCpf))
            ->setActive(true)
            ->setSynchroniseLe($maintenant);
    }

    /**
     * Prix unique par formation (QE-10) ; PROVISOIRE (QSO-4) : on prend le premier tarif du produit ; prix par participant = somme des lignes
     * du budget (prix unitaire HT × quantité), TVA de la première ligne.
     *
     * @param list<array<string, mixed>> $tarifs
     *
     * @return array{0: ?int, 1: float}
     */
    private function price(array $tarifs): array
    {
        $budget = $tarifs[0]['budget'] ?? [];
        if ([] === $budget) {
            return [null, 20.0];
        }

        $totalHt = 0.0;
        foreach ($budget as $ligne) {
            $totalHt += (float) $ligne['prixUnitaireHT'] * (float) $ligne['quantite'];
        }

        return [(int) round($totalHt * 100), (float) ($budget[0]['tva'] ?? 20)];
    }

    /**
     * Adresse de la fiche = référence SmartOF (ex. /formation/t68-25), stable pour les liens du site vitrine.
     *
     * @param array<string, Course> $actives
     * @param array<string, Course> $existantes
     */
    private function assignSlugs(array $actives, array $existantes): void
    {
        $pris = [];
        foreach ($existantes as $uid => $formation) {
            if (!isset($actives[$uid])) {
                $pris[$formation->getSlug()] = true;
            }
        }

        foreach ($actives as $uid => $formation) {
            $slug = '' !== $formation->getReference()
                ? $this->slugger->slug($formation->getReference())->lower()->toString()
                : $uid;
            if (isset($pris[$slug])) {
                $slug .= '-'.substr($uid, 0, 8);
            }
            $formation->setSlug($slug);
            $pris[$slug] = true;
        }
    }

    /** @param array<string, Course> $formations */
    private function syncSessions(array $formations, \DateTimeImmutable $maintenant, SyncReport $rapport): void
    {
        $salles = [];
        foreach ($this->smartof->salleFormations() as $salle) {
            $salles[$salle['salleFormationUid']] = (string) ($salle['meta']['nom'] ?? '');
        }

        $remplissages = [];
        foreach ($this->smartof->sessionsOuvertes() as $ouverte) {
            $remplissages[$ouverte['session']['sessionUid']] = $ouverte['remplissage'];
        }

        $existantes = $this->sessions->findAllIndexedBySmartofUid();
        $vues = [];

        foreach (array_chunk(array_keys($formations), self::LOT_PRODUITS) as $lot) {
            $filtres = [
                'produitInstance.produitFormationUid' => ['any' => implode(',', $lot)],
                'archive' => ['eq' => 'false'],
                'meta.dateFin' => ['gte' => $maintenant->format('Y-m-d')],
            ];

            foreach ($this->smartof->sessionFormations($filtres) as $donnees) {
                $uid = $donnees['sessionUid'];
                $formation = $formations[$donnees['produitInstance']['produitFormationUid']];
                $session = $existantes[$uid] ?? new Session($uid, $formation);
                $remplissage = $remplissages[$uid] ?? null;

                $session
                    ->setFormation($formation)
                    ->setNom((string) ($donnees['meta']['nom'] ?? ''))
                    ->setDateDebut($this->date($donnees['meta']['dateDebut']))
                    ->setDateFin($this->date($donnees['meta']['dateFin']))
                    ->setLieu($this->location($donnees['meta']['lieu'] ?? [], $salles))
                    ->setOuverte(null !== $remplissage)
                    ->setInscritsSmartof((int) ($remplissage['inscrits'] ?? 0))
                    ->setLimitePlaces(is_numeric($remplissage['limite'] ?? null) ? (int) $remplissage['limite'] : null)
                    ->setSynchroniseLe($maintenant);

                $this->em->persist($session);
                $vues[$uid] = true;
                $rapport->sessionsOuvertes += null !== $remplissage ? 1 : 0;
            }
        }

        foreach ($existantes as $uid => $session) {
            if (!isset($vues[$uid]) && $session->isOuverte()) {
                $session->setOuverte(false);
                ++$rapport->sessionsFermees;
            }
        }

        $rapport->sessions = \count($vues);
    }

    /**
     * @param array<string, mixed>  $lieu  union discriminée SmartOF (libre / connu / client / of / virtuel)
     * @param array<string, string> $salles
     */
    private function location(array $lieu, array $salles): string
    {
        return match ($lieu['typeLieu'] ?? null) {
            'libre' => (string) ($lieu['lieuLibre'] ?? ''),
            'connu' => $salles[$lieu['salleFormationUid'] ?? ''] ?? '',
            'client' => 'Dans vos locaux (intra-entreprise)',
            'of' => 'Dans les locaux de COPROTEC',
            'virtuel' => 'Classe virtuelle',
            default => '',
        };
    }

    private function date(string $iso): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('Europe/Paris'));
    }

    /** @return array{0: string, 1: string} « champ=valeur » */
    private function parseFilter(string $filtre): array
    {
        $parts = explode('=', $filtre, 2);
        if (2 !== \count($parts) || '' === trim($parts[0])) {
            throw new \InvalidArgumentException(\sprintf('Filtre SmartOF invalide « %s » : format attendu « champ=valeur ».', $filtre));
        }

        return [trim($parts[0]), trim($parts[1])];
    }

    /** @param array<string, mixed> $document */
    private function value(array $document, string $champ): string
    {
        $valeur = $document;
        foreach (explode('.', $champ) as $cle) {
            $valeur = \is_array($valeur) ? ($valeur[$cle] ?? null) : null;
        }

        return \is_scalar($valeur) ? (string) $valeur : '';
    }
}
