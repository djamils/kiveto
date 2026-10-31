<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\ListPortalPublishablePrescriptions;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One batch of the signed prescriptions the Client Portal go-live import
 * publishes, all clinics together: issued at or after $issuedFrom, with a
 * known owner, ordered by id and resumed after $afterId.
 */
final readonly class ListPortalPublishablePrescriptions implements QueryInterface
{
    public function __construct(
        public \DateTimeImmutable $issuedFrom,
        public ?string $afterId,
        public int $limit,
    ) {
    }
}
