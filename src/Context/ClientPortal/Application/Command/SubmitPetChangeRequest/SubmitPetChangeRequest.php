<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SubmitPetChangeRequest;

use App\Shared\Application\Bus\CommandInterface;

/**
 * `POST …/pets/{id}/change-requests`: asks the clinic to change a field of
 * the pet's file (the animal itself never changes). Answers the request id.
 * The 10-per-24-hours quota is the caller's (`PortalRequestQuotaInterface`);
 * the handler caps the open requests per pet and field at 3.
 *
 * @throws \App\Context\ClientPortal\Domain\Exception\PetNotFoundException              not one of the account's pets (404)
 * @throws \App\Context\ClientPortal\Domain\Exception\PetNotEligibleException           deceased, missing or archived (422 `pet_not_eligible`)
 * @throws \App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException     field, proposedValue, measuredOn, note (422)
 * @throws \App\Context\ClientPortal\Domain\Exception\PortalRequestRateLimitedException 3 open on that pet and field (429 `rate_limited`)
 */
final readonly class SubmitPetChangeRequest implements CommandInterface
{
    /**
     * @param string              $field      name|weight|sterilization|chip_number|other
     * @param ?\DateTimeImmutable $measuredOn weight only, a date (clinic-local day), default the submit day
     */
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $petId,
        public string $field,
        public string $proposedValue,
        public ?\DateTimeImmutable $measuredOn = null,
        public ?string $note = null,
    ) {
    }
}
