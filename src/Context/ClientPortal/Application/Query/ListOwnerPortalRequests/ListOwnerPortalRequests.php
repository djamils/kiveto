<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerPortalRequests;

use App\Shared\Application\Bus\QueryInterface;

/**
 * `GET /clinics/{c}/requests`: the pet change and renewal requests the
 * account sent in the clinic, newest first. Answers
 * `CursorPage<OwnerPortalRequest>`; `InvalidCursorException`.
 */
final readonly class ListOwnerPortalRequests implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
