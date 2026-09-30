<?php

namespace App\Smartof;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Accès à l'API REST v2 SmartOF (https://developers.smartof.fr).
 *
 * Conventions de l'API : Bearer `smk_…`, chemins /v2/…, pagination par curseur (≤ 1000),
 * filtres `filter[champ][op]=valeur`, schémas stricts (clé inconnue = 400, "" et jamais null).
 * Avec SMARTOF_FAKE=1 (dev, tests), les appels sont servis par FakeSmartofApi.
 */
class SmartofClient
{
    private const PAGE_SIZE = 1000;

    private HttpClientInterface $http;

    public function __construct(
        HttpClientInterface $httpClient,
        FakeSmartofApi $fakeApi,
        #[Autowire('%env(bool:SMARTOF_FAKE)%')] bool $fake,
        #[Autowire('%env(SMARTOF_BASE_URL)%')] string $baseUrl,
        #[Autowire('%env(SMARTOF_API_KEY)%')] string $apiKey,
    ) {
        $this->http = $fake
            ? new MockHttpClient($fakeApi, FakeSmartofApi::BASE_URL.'/')
            : $httpClient->withOptions([
                'base_uri' => rtrim($baseUrl, '/').'/',
                'auth_bearer' => $apiKey,
                'timeout' => 20,
            ]);
    }

    /**
     * @param array<string, array<string, string>> $filters ex. ['archived' => ['eq' => 'false']]
     *
     * @return iterable<array<string, mixed>>
     */
    public function produitFormations(array $filters = []): iterable
    {
        return $this->paginate('v2/produit_formations', $filters);
    }

    /**
     * @param array<string, array<string, string>> $filters
     *
     * @return iterable<array<string, mixed>>
     */
    public function sessionFormations(array $filters = []): iterable
    {
        return $this->paginate('v2/session_formations', $filters);
    }

    /** @return iterable<array<string, mixed>> */
    public function salleFormations(): iterable
    {
        return $this->paginate('v2/salle_formations');
    }

    /**
     * Sessions dont l'inscription est ouverte, avec leur remplissage (endpoint non paginé).
     *
     * @return list<array<string, mixed>>
     */
    public function sessionsOuvertes(): array
    {
        return $this->request('GET', 'v2/sessions_ouvertes')['sessionOuvertes'] ?? [];
    }

    /**
     * @param array<string, array<string, string>> $filters
     *
     * @return iterable<array<string, mixed>>
     */
    public function paginate(string $path, array $filters = []): iterable
    {
        $cursor = null;
        do {
            $query = ['limit' => self::PAGE_SIZE];
            if ([] !== $filters) {
                $query['filter'] = $filters;
            }
            if (null !== $cursor) {
                $query['cursor'] = $cursor;
            }

            $page = $this->request('GET', $path, ['query' => $query]);
            yield from $page['data'] ?? [];

            $cursor = $page['pageInfo']['nextCursor'] ?? null;
        } while (($page['pageInfo']['hasMore'] ?? false) && null !== $cursor);
    }

    /**
     * @param array<string, mixed> $options options HttpClient (query, json…)
     *
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $response = $this->http->request($method, $path, $options);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw SmartofException::fromResponse($method, $path, $status, $this->decode($response));
        }

        return 204 === $status ? [] : $this->decode($response);
    }

    /** @return array<string, mixed> */
    private function decode(ResponseInterface $response): array
    {
        try {
            return $response->toArray(false);
        } catch (\Throwable) {
            return ['message' => $response->getContent(false)];
        }
    }
}
