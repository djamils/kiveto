<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPublishedDocuments;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One page of the documents an owner account sees in a clinic. Answers a
 * CursorPage<PortalDocument>; a cursor of another shape throws
 * InvalidCursorException.
 */
final readonly class ListPublishedDocuments implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public ?string $type = null,
        public ?string $petId = null,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
