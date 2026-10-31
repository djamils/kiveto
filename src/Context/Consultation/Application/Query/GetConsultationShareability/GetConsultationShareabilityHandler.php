<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationShareability;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientAnimalOwnershipInterface;
use App\Context\Consultation\Application\Port\PractitionerDirectoryInterface;
use App\Context\Consultation\Application\Service\Documents\DocumentLabels;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Checked in order: closed, patient linked to an animal, animal with an
 * active primary owner. The ids are returned even when not shareable, so the
 * caller can explain the refusal.
 */
#[AsMessageHandler]
final readonly class GetConsultationShareabilityHandler
{
    public function __construct(
        private ConsultationReadRepositoryInterface $consultations,
        private PatientAnimalOwnershipInterface $ownerships,
        private PractitionerDirectoryInterface $practitioners,
    ) {
    }

    public function __invoke(GetConsultationShareability $query): ?ConsultationShareability
    {
        try {
            $consultation = $this->consultations->findById(ConsultationId::fromString($query->consultationId), ClinicId::fromString($query->clinicId));
        } catch (\DomainException) {
            return null;
        } catch (\InvalidArgumentException) {
            return null;
        }

        $ownership = $this->ownerships->ownershipOf($query->clinicId, $consultation->patientId);

        $reason = match (true) {
            !$consultation->isClosed()                => ConsultationShareability::NOT_CLOSED,
            null === $ownership->animalId             => ConsultationShareability::NO_ANIMAL,
            null === $ownership->primaryOwnerClientId => ConsultationShareability::NO_PRIMARY_OWNER,
            default                                   => null,
        };

        return new ConsultationShareability(
            shareable: null === $reason,
            reason: $reason,
            patientId: $consultation->patientId,
            animalId: $ownership->animalId,
            primaryOwnerClientId: $ownership->primaryOwnerClientId,
            startedAtUtc: self::utc($consultation->startedAtUtc),
            closedAtUtc: null !== $consultation->closedAtUtc ? self::utc($consultation->closedAtUtc) : null,
            practitionerName: DocumentLabels::practitioner($this->practitioners->find($query->clinicId, $consultation->practitionerUserId)),
        );
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
