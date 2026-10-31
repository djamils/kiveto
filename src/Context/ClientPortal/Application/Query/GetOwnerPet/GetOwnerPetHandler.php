<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerPet;

use App\Context\ClientPortal\Application\Port\AnimalWeightsInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\ConsultationWeightsInterface;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\OwnerPet;
use App\Context\ClientPortal\Application\Service\CareItemStatusPolicy;
use App\Context\ClientPortal\Application\Service\OwnerPets;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The latest weight is the most recent of the animal file's records and the
 * vitals of CLOSED consultations, compared on the clinic-local day; on the
 * same day the animal file wins. The referent vet is the name snapshotted
 * on the latest report shared with the account.
 */
#[AsMessageHandler]
final readonly class GetOwnerPetHandler
{
    public function __construct(
        private OwnerPets $pets,
        private AnimalWeightsInterface $animalWeights,
        private ConsultationWeightsInterface $consultationWeights,
        private PublishedDocumentReadModelInterface $documents,
        private ClinicProfileInterface $clinics,
    ) {
    }

    public function __invoke(GetOwnerPet $query): ?OwnerPetDetails
    {
        $pet = $this->pets->find($query->userId, $query->clinicId, $query->petId);

        if (null === $pet) {
            return null;
        }

        $latestReport = $this->documents->listable($query->userId, $query->clinicId, DocumentType::REPORT->value, $pet->id, null, 1)[0] ?? null;

        return new OwnerPetDetails(
            OwnerPet::of($pet),
            $this->lastWeight($query->clinicId, $pet->id),
            self::masked($pet->microchipNumber),
            $latestReport?->practitionerName,
        );
    }

    private function lastWeight(string $clinicId, string $petId): ?PetWeight
    {
        $record    = $this->animalWeights->latestOf($clinicId, $petId);
        $visit     = $this->consultationWeights->latestClosedOf($clinicId, $petId);
        $fromVisit = null === $visit ? null : new PetWeight(
            $visit->weightGrams,
            CareItemStatusPolicy::dayIn($visit->measuredAtUtc, new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone)),
        );

        if (null === $record) {
            return $fromVisit;
        }

        if (null !== $fromVisit && $fromVisit->at->format('Y-m-d') > $record->measuredOn->format('Y-m-d')) {
            return $fromVisit;
        }

        return new PetWeight($record->weightGrams, $record->measuredOn);
    }

    private static function masked(?string $chip): ?string
    {
        $chip = null === $chip ? '' : mb_trim($chip);

        return '' === $chip ? null : '•••• ' . mb_substr($chip, -4);
    }
}
