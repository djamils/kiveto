<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class ReportShareability
{
    public const string NOT_CLOSED       = 'not_closed';
    public const string NO_ANIMAL        = 'no_animal';
    public const string NO_PRIMARY_OWNER = 'no_primary_owner';

    /**
     * @param ?string $refusal one of the constants, null when shareable
     */
    public function __construct(
        public ?string $refusal,
        public string $patientId,
        public ?string $animalId,
        public ?string $primaryOwnerClientId,
        public \DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $closedAt,
        public ?string $practitionerName,
    ) {
    }
}
