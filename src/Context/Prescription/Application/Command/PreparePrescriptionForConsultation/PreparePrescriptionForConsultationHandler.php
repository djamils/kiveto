<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\PreparePrescriptionForConsultation;

use App\Context\Prescription\Application\Port\ConsultationPrescriptionLineDto;
use App\Context\Prescription\Application\Port\ConsultationPrescriptionProviderInterface;
use App\Context\Prescription\Application\Port\DrugDetailsDto;
use App\Context\Prescription\Application\Port\DrugRegistryInterface;
use App\Context\Prescription\Application\Port\PatientDirectoryInterface;
use App\Context\Prescription\Application\Port\PatientSnapshotDto;
use App\Context\Prescription\Application\Port\PrescriberDirectoryInterface;
use App\Context\Prescription\Domain\Exception\InvalidPatientSnapshotException;
use App\Context\Prescription\Domain\Exception\InvalidPrescriberException;
use App\Context\Prescription\Domain\Prescription;
use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\DrugPrescriptionClass;
use App\Context\Prescription\Domain\ValueObject\OwnerId;
use App\Context\Prescription\Domain\ValueObject\OwnerSnapshot;
use App\Context\Prescription\Domain\ValueObject\PatientSnapshot;
use App\Context\Prescription\Domain\ValueObject\Prescriber;
use App\Context\Prescription\Domain\ValueObject\PrescriberId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLine;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLineId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Turns what the practitioner typed in the cockpit into an ordonnance.
 *
 * Idempotent on purpose: the cockpit can be revisited as often as the
 * practitioner likes, and a consultation that already has a signed prescription
 * hands back that one rather than drafting a second. Only a cancelled one is
 * replaced.
 */
#[AsMessageHandler]
final readonly class PreparePrescriptionForConsultationHandler
{
    public function __construct(
        private PrescriptionRepositoryInterface $prescriptions,
        private ConsultationPrescriptionProviderInterface $consultationLines,
        private PrescriberDirectoryInterface $prescribers,
        private PatientDirectoryInterface $patients,
        private DrugRegistryInterface $drugs,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PreparePrescriptionForConsultation $command): string
    {
        $clinicId       = ClinicId::fromString($command->clinicId);
        $consultationId = ConsultationId::fromString($command->consultationId);

        $existing = $this->prescriptions->findForConsultation($clinicId, $consultationId);

        if (null !== $existing && !$existing->status()->isEditable()) {
            return $existing->id()->toString();
        }

        $now     = $this->clock->now();
        $patient = $this->patients->forConsultation($command->consultationId, $command->clinicId);

        if (null === $patient) {
            throw new InvalidPatientSnapshotException(
                'This consultation has no identified patient to prescribe for.',
            );
        }

        // Both parties are resolved before anything is built: a draft that
        // cannot name its prescriber or its patient should never come into
        // being at all.
        $prescriber = $this->prescriberFor($command);

        // A draft is rebuilt rather than patched: the practitioner may have
        // added or dropped medicines since the last time they looked at it.
        $prescription = Prescription::draft(
            id: null !== $existing
                ? $existing->id()
                : PrescriptionId::fromString($this->uuidGenerator->generate()),
            clinicId: $clinicId,
            consultationId: $consultationId,
            prescriber: $prescriber,
            patient: $this->patientFor($patient),
            owner: $this->ownerFor($patient),
            now: $now,
        );

        foreach ($this->consultationLines->linesForConsultation($command->consultationId, $command->clinicId) as $line) {
            $prescription->addLine($this->toPrescriptionLine($line, $command->clinicId), $now);
        }

        $this->prescriptions->save($prescription);

        return $prescription->id()->toString();
    }

    private function prescriberFor(PreparePrescriptionForConsultation $command): Prescriber
    {
        $snapshot = $this->prescribers->forUser($command->prescriberUserId, $command->clinicId);

        if (null === $snapshot) {
            throw new InvalidPrescriberException(
                'This practitioner has no profile in the clinic and cannot prescribe here.',
            );
        }

        return Prescriber::create(
            PrescriberId::fromString($snapshot->userId),
            $snapshot->displayName,
            $snapshot->registrationNumber,
            $snapshot->professionalTitle,
        );
    }

    private function patientFor(PatientSnapshotDto $snapshot): PatientSnapshot
    {
        return PatientSnapshot::create(
            animalId: $snapshot->animalId,
            name: $snapshot->animalName,
            species: $snapshot->species,
            breed: $snapshot->breed,
            birthDate: $snapshot->birthDate,
            microchipNumber: $snapshot->microchipNumber,
        );
    }

    private function ownerFor(PatientSnapshotDto $snapshot): OwnerSnapshot
    {
        if (null === $snapshot->ownerName) {
            return OwnerSnapshot::unknown();
        }

        return OwnerSnapshot::create(
            null !== $snapshot->ownerClientId ? OwnerId::fromString($snapshot->ownerClientId) : null,
            $snapshot->ownerName,
            $snapshot->ownerAddressLines,
        );
    }

    private function toPrescriptionLine(ConsultationPrescriptionLineDto $line, string $clinicId): PrescriptionLine
    {
        $details = null !== $line->articleId
            ? $this->drugs->detailsOf($line->articleId, $clinicId)
            : DrugDetailsDto::unknown();

        return PrescriptionLine::create(
            id: PrescriptionLineId::fromString($this->uuidGenerator->generate()),
            articleId: $line->articleId,
            code: $line->code,
            label: $line->label,
            pharmaceuticalForm: $details->pharmaceuticalForm,
            activeSubstances: $details->activeSubstances,
            dose: $line->dose,
            frequency: $line->frequency,
            durationDays: $line->durationDays,
            route: $line->route,
            quantity: $line->quantity,
            prescriptionClass: DrugPrescriptionClass::tryFrom($details->prescriptionClass)
                ?? DrugPrescriptionClass::NONE,
        );
    }
}
