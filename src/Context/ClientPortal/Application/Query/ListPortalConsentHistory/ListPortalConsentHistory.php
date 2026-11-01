<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPortalConsentHistory;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One page of the consent history of the owner's confirmed-linked client
 * files of a clinic that are not archived, newest first. Answers a
 * CursorPage<PortalConsentHistoryItem>; throws ClinicNotLinkedException
 * when there is no such file and InvalidCursorException for a cursor of
 * another shape.
 */
final readonly class ListPortalConsentHistory implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
