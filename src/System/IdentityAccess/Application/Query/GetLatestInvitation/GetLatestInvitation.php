<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetLatestInvitation;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The invitation link a person was sent last and has not used, for the
 * console's « Invitation envoyée le … · expire le … ». Null when none.
 */
final readonly class GetLatestInvitation implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
