<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetInvitationStatuses;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Where each of these users stands with the invitation of this clinic:
 * PENDING_VALID (invited, link still usable), PENDING_EXPIRED (invited, no
 * usable link of this clinic left) or ACTIVE. Unknown and disabled accounts
 * are absent from the answer.
 */
final readonly class GetInvitationStatuses implements QueryInterface
{
    public const string PENDING_VALID   = 'PENDING_VALID';
    public const string PENDING_EXPIRED = 'PENDING_EXPIRED';
    public const string ACTIVE          = 'ACTIVE';

    /**
     * @param list<string> $userIds
     */
    public function __construct(
        public string $clinicId,
        public array $userIds,
    ) {
    }
}
