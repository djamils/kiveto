<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The client file was archived: its pending invitations are revoked and its
 * links to owner accounts withdrawn. Idempotent; unarchiving restores
 * nothing (the clinic invites again).
 */
final readonly class RevokeClientPortalAccess implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
