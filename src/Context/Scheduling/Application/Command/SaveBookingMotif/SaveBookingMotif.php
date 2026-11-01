<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\SaveBookingMotif;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Creates a booking motif (`motifId` null) or updates one of the clinic.
 * The handler returns the motif id.
 */
final readonly class SaveBookingMotif implements CommandInterface
{
    /**
     * @param string       $confirmationKnownClient `auto|manual`
     * @param list<string> $allowedBlockTypes       `PlanningBlockType` values accepting appointments
     * @param list<string> $practitionerIds         user ids of practitioners eligible at the clinic
     * @param string|null  $preventiveCareKind      `vaccination|internal_parasite|external_parasite|other`
     */
    public function __construct(
        public string $clinicId,
        public ?string $motifId,
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
    ) {
    }
}
