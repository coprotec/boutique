<?php

namespace App\Catalog;

final class SyncReport
{
    public int $formations = 0;
    public int $formationsRetirees = 0;
    public int $sessions = 0;
    public int $sessionsOuvertes = 0;
    public int $sessionsFermees = 0;

    /** @return array<string, int> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
