<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPetTimeline;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One page of the visits of a pet whose report was shared with the owner
 * account, latest close first. Answers a CursorPage<TimelineEntry>; throws
 * PetNotFoundException when the pet is not visible to the account and
 * InvalidCursorException for a cursor of another shape.
 */
final readonly class ListPetTimeline implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $petId,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
