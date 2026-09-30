<?php

namespace App\Smartof;

/**
 * Erreur renvoyée par l'API SmartOF. `message` et `details` sont conçus pour être journalisés tels quels.
 */
class SmartofException extends \RuntimeException
{
    /** @param array<string, mixed> $body */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $body = [],
    ) {
        parent::__construct($message, $status);
    }

    /** @param array<string, mixed> $body */
    public static function fromResponse(string $method, string $path, int $status, array $body): self
    {
        $detail = $body['message'] ?? 'réponse sans message';

        return new self(\sprintf('SmartOF %s /%s : HTTP %d — %s', $method, $path, $status, $detail), $status, $body);
    }

    /** 400/404/409 : erreurs déterministes, inutile de relancer sans corriger (cf. doc SmartOF « Erreurs »). */
    public function isDefinitive(): bool
    {
        return \in_array($this->status, [400, 403, 404, 409], true);
    }
}
