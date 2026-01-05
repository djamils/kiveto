<?php

declare(strict_types=1);

namespace App\Shared\Application\Security;

/**
 * Context storing the current actor (user) ID for auditing purposes.
 * Can be populated from Symfony Security token or set manually.
 */
final class ActorContext
{
    private ?string $actorId = null;

    public function set(?string $actorId): void
    {
        $this->actorId = $actorId;
    }

    public function get(): ?string
    {
        return $this->actorId;
    }
}
