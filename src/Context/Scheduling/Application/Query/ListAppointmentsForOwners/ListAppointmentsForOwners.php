<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForOwners;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One page of the appointments of some clients or of some animals (the
 * Portal's « Mes rendez-vous »), keyset on `(starts_at_utc, id)`. Answers a
 * CursorPage<PortalAppointmentItem>; a cursor of another shape throws
 * InvalidCursorException. The free-text reason and notes are never read.
 */
final readonly class ListAppointmentsForOwners implements QueryInterface
{
    /**
     * @param list<string> $ownerIds  client ids
     * @param list<string> $animalIds
     */
    public function __construct(
        public string $clinicId,
        public array $ownerIds,
        public array $animalIds,
        public AppointmentListScope $scope,
        public ?string $cursor,
        public ?int $limit,
        public \DateTimeImmutable $nowUtc,
    ) {
    }
}
