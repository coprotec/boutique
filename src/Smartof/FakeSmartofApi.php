<?php

namespace App\Smartof;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

/**
 * API SmartOF simulée pour le dev et les tests (SMARTOF_FAKE=1), en attendant l'instance de test (QSO-1).
 *
 * Lecture : données de fixtures/smartof/fake_api.json. Écriture (entreprises, apprenants, commanditaires) :
 * conservée dans var/smartof-fake/<env>.json pour pouvoir inspecter ce que la boutique a envoyé.
 * Seuls les comportements documentés utiles à la boutique sont reproduits.
 */
class FakeSmartofApi
{
    public const BASE_URL = 'https://smartof.fake/external/api';

    private const WRITABLE = ['entreprises', 'apprenants', 'commanditaires'];

    /** @var array<string, mixed>|null */
    private ?array $fixtures = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/fixtures/smartof/fake_api.json')] private readonly string $fixturesFile,
        #[Autowire('%kernel.project_dir%/var/smartof-fake/%kernel.environment%.json')] private readonly string $storeFile,
    ) {
    }

    /** @param array<string, mixed> $options */
    public function __invoke(string $method, string $url, array $options): MockResponse
    {
        $parts = parse_url($url);
        $path = substr($parts['path'] ?? '', \strlen((string) parse_url(self::BASE_URL, \PHP_URL_PATH)) + 1);
        parse_str($parts['query'] ?? '', $query);

        if (!str_starts_with($path, 'v2/')) {
            return $this->json(404, ['error' => 'Not Found', 'message' => "Route inconnue : $path"]);
        }
        $collection = substr($path, 3);

        return match (true) {
            'GET' === $method && 'sessions_ouvertes' === $collection => $this->json(200, ['sessionOuvertes' => $this->sessionsOuvertes()]),
            'GET' === $method && null !== $this->documents($collection) => $this->json(200, $this->page($collection, $query['filter'] ?? [])),
            'POST' === $method && 'commanditaires' === $collection => $this->createCommanditaire($this->body($options)),
            'POST' === $method && \in_array($collection, self::WRITABLE, true) => $this->create($collection, $this->body($options)),
            default => $this->json(405, ['error' => 'Method Not Allowed', 'message' => "$method $path non simulé"]),
        };
    }

    /** Vide les écritures simulées (tests). */
    public function reset(): void
    {
        if (is_file($this->storeFile)) {
            unlink($this->storeFile);
        }
    }

    /** @return list<array<string, mixed>> documents créés dans une collection (tests, inspection) */
    public function created(string $collection): array
    {
        return $this->store()[$collection] ?? [];
    }

    /** @return list<array<string, mixed>>|null */
    private function documents(string $collection): ?array
    {
        $fixtures = $this->fixtures();
        if (\in_array($collection, self::WRITABLE, true)) {
            return $this->store()[$collection] ?? [];
        }

        return isset($fixtures[$collection]) && 'sessions_ouvertes' !== $collection ? $fixtures[$collection] : null;
    }

    /**
     * @param array<string, array<string, string>> $filters
     *
     * @return array<string, mixed>
     */
    private function page(string $collection, array $filters): array
    {
        $data = array_values(array_filter(
            $this->documents($collection) ?? [],
            fn (array $doc): bool => $this->matches($doc, $filters),
        ));

        return ['data' => $data, 'pageInfo' => ['nextCursor' => null, 'hasMore' => false, 'limit' => 1000]];
    }

    /** @return list<array<string, mixed>> */
    private function sessionsOuvertes(): array
    {
        $sessions = array_column($this->fixtures()['session_formations'], null, 'sessionUid');
        $inscritsBoutique = [];
        foreach ($this->store()['commanditaires'] ?? [] as $commanditaire) {
            $uid = $commanditaire['sessionUid'];
            $inscritsBoutique[$uid] = ($inscritsBoutique[$uid] ?? 0) + \count($commanditaire['apprenantUids']);
        }

        $result = [];
        foreach ($this->fixtures()['sessions_ouvertes'] as $i => $ouverte) {
            $session = $sessions[$ouverte['sessionUid']];
            $result[] = [
                'inscriptionUid' => \sprintf('1a000000-0000-4000-8000-%012d', $i + 1),
                'session' => [
                    'sessionUid' => $session['sessionUid'],
                    'nom' => $session['meta']['nom'],
                    'dateDebut' => $session['meta']['dateDebut'],
                    'dateFin' => $session['meta']['dateFin'],
                    'status' => $session['meta']['status'],
                ],
                'remplissage' => [
                    'enAttente' => 0,
                    'inscrits' => $ouverte['inscrits'] + ($inscritsBoutique[$ouverte['sessionUid']] ?? 0),
                    'limite' => $ouverte['limite'],
                ],
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $body */
    private function create(string $collection, array $body): MockResponse
    {
        if (isset($body[$this->uidKey($collection)])) {
            return $this->json(400, ['error' => 'Bad Request', 'message' => 'Les UID sont générés par SmartOF.']);
        }

        $body[$this->uidKey($collection)] = Uuid::v4()->toRfc4122();
        $body['createdAt'] = (new \DateTimeImmutable())->format(\DATE_RFC3339_EXTENDED);
        $this->append($collection, $body);

        return $this->json(201, $body);
    }

    /** @param array<string, mixed> $body */
    private function createCommanditaire(array $body): MockResponse
    {
        $ouverte = null;
        foreach ($this->sessionsOuvertes() as $candidate) {
            if ($candidate['session']['sessionUid'] === ($body['sessionUid'] ?? null)) {
                $ouverte = $candidate;
            }
        }
        if (null === $ouverte) {
            return $this->json(404, ['error' => 'Not Found', 'message' => 'Session introuvable ou sans inscription ouverte.']);
        }

        $apprenants = array_column($this->store()['apprenants'] ?? [], null, 'apprenantUid');
        foreach ($body['apprenantUids'] ?? [] as $uid) {
            if (!isset($apprenants[$uid])) {
                return $this->json(404, ['error' => 'Not Found', 'message' => "Apprenant $uid introuvable."]);
            }
        }

        // Comportement réel inconnu (QSO-5) : on simule un refus quand la limite de places serait dépassée.
        $limite = $ouverte['remplissage']['limite'];
        if (\is_int($limite) && $ouverte['remplissage']['inscrits'] + \count($body['apprenantUids'] ?? []) > $limite) {
            return $this->json(409, ['error' => 'Conflict', 'message' => 'Limite de places de la session atteinte.']);
        }

        $body['commanditaireUid'] = Uuid::v4()->toRfc4122();
        $this->append('commanditaires', $body);

        return $this->json(201, ['commanditaireUids' => [$body['commanditaireUid']]]);
    }

    /**
     * @param array<string, mixed>                 $doc
     * @param array<string, array<string, string>> $filters
     */
    private function matches(array $doc, array $filters): bool
    {
        foreach ($filters as $field => $operations) {
            $value = $this->resolve($doc, $field);
            foreach ($operations as $op => $expected) {
                $ok = match ($op) {
                    'eq' => $this->scalar($value) === $expected,
                    'neq' => $this->scalar($value) !== $expected,
                    'any' => \in_array($this->scalar($value), explode(',', $expected), true),
                    'in' => \is_array($value) && \in_array($expected, $value, true),
                    'gte' => $this->compare($value, $expected) >= 0,
                    'gt' => $this->compare($value, $expected) > 0,
                    'lte' => $this->compare($value, $expected) <= 0,
                    'lt' => $this->compare($value, $expected) < 0,
                    'contains' => str_contains(mb_strtolower((string) $value), mb_strtolower($expected)),
                    default => false,
                };
                if (!$ok) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<string, mixed> $doc */
    private function resolve(array $doc, string $field): mixed
    {
        $value = $doc;
        foreach (explode('.', $field) as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    private function scalar(mixed $value): string
    {
        return \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }

    private function compare(mixed $value, string $expected): int
    {
        if (\is_string($value) && false !== strtotime($value) && false !== strtotime($expected)) {
            return strtotime($value) <=> strtotime($expected);
        }

        return (float) $value <=> (float) $expected;
    }

    private function uidKey(string $collection): string
    {
        return rtrim($collection, 's').'Uid';
    }

    /** @return array<string, mixed> */
    private function fixtures(): array
    {
        if (null === $this->fixtures) {
            $json = (string) file_get_contents($this->fixturesFile);
            // Dates relatives « +12 days 08:30 » → ISO 8601 avec l'offset de Paris, comme l'API.
            $json = preg_replace_callback('/"([+-]\d+ days \d{2}:\d{2})"/', static fn (array $m): string => '"'.(new \DateTimeImmutable('today '.$m[1], new \DateTimeZone('Europe/Paris')))->format('Y-m-d\TH:i:s.vP').'"', $json);
            $this->fixtures = json_decode((string) $json, true, flags: \JSON_THROW_ON_ERROR);
        }

        return $this->fixtures;
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function store(): array
    {
        return is_file($this->storeFile) ? json_decode((string) file_get_contents($this->storeFile), true) : [];
    }

    /** @param array<string, mixed> $doc */
    private function append(string $collection, array $doc): void
    {
        $store = $this->store();
        $store[$collection][] = $doc;
        if (!is_dir(\dirname($this->storeFile))) {
            mkdir(\dirname($this->storeFile), 0o775, true);
        }
        file_put_contents($this->storeFile, json_encode($store, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $options */
    private function body(array $options): array
    {
        return json_decode((string) ($options['body'] ?? '{}'), true) ?? [];
    }

    /** @param array<string, mixed> $data */
    private function json(int $status, array $data): MockResponse
    {
        return new MockResponse(json_encode($data, \JSON_UNESCAPED_UNICODE), [
            'http_code' => $status,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }
}
