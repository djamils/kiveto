<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\AnonymisePortalUser;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Last step of the erasure of a disabled owner account: phone and e-mail
 * erased (`anonymisedAt` set), then the phone's mutex row deleted — the
 * phone is free for a new account. Idempotent.
 */
final readonly class AnonymisePortalUser implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
