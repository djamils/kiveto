<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/** The owner behind a Portal API access token, identified by the token's `sub`. */
final readonly class PortalApiUser implements UserInterface
{
    public function __construct(private string $id)
    {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->id) {
            throw new \LogicException('An owner is identified by a non-empty id.');
        }

        return $this->id;
    }

    public function getRoles(): array
    {
        return ['ROLE_PORTAL_OWNER'];
    }

    public function eraseCredentials(): void
    {
        // no credentials are held
    }
}
