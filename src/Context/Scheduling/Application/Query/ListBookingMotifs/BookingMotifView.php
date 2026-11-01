<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListBookingMotifs;

final readonly class BookingMotifView
{
    /**
     * @param list<string> $allowedBlockTypes `PlanningBlockType` values
     * @param list<string> $practitionerIds
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?string $description,
        public int $durationMin,
        public bool $onlineEnabled,
        public string $confirmationKnownClient,
        public array $allowedBlockTypes,
        public array $practitionerIds,
        public ?string $preventiveCareKind,
        public bool $newClientsAllowed,
        public int $sortOrder,
        public ?\DateTimeImmutable $archivedAt,
    ) {
    }
}
