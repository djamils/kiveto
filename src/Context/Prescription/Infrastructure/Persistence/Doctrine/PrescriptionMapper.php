<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Persistence\Doctrine;

use App\Context\Prescription\Domain\Prescription;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\OwnerId;
use App\Context\Prescription\Domain\ValueObject\OwnerSnapshot;
use App\Context\Prescription\Domain\ValueObject\PatientSnapshot;
use App\Context\Prescription\Domain\ValueObject\Prescriber;
use App\Context\Prescription\Domain\ValueObject\PrescriberId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLine;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLineId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionNumber;
use App\Context\Prescription\Domain\ValueObject\Renewal;
use App\Context\Prescription\Domain\ValueObject\RenewalId;
use App\Context\Prescription\Domain\ValueObject\Renewals;
use App\Context\Prescription\Domain\ValueObject\Validity;
use App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity\PrescriptionEntity;
use App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity\PrescriptionLineEntity;
use App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity\RenewalEntity;
use Symfony\Component\Uid\Uuid;

final readonly class PrescriptionMapper
{
    /**
     * @param list<PrescriptionLineEntity> $lineEntities
     * @param list<RenewalEntity>          $renewalEntities
     */
    public function toDomain(
        PrescriptionEntity $entity,
        array $lineEntities,
        array $renewalEntities,
    ): Prescription {
        $ownerClientId = $entity->getOwnerClientId();
        $issuedAt      = $entity->getIssuedAt();
        $validUntil    = $entity->getValidUntil();

        $validity = null !== $issuedAt && null !== $validUntil
            ? Validity::reconstitute($issuedAt, $validUntil, $entity->getValiditySpanDays() ?? 1)
            : null;

        $animalId = $entity->getPatientAnimalId();

        return Prescription::reconstitute(
            id: PrescriptionId::fromString($entity->getId()->toRfc4122()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toRfc4122()),
            consultationId: ConsultationId::fromString($entity->getConsultationId()->toRfc4122()),
            number: null !== $entity->getNumber() ? PrescriptionNumber::fromString($entity->getNumber()) : null,
            status: $entity->getStatus(),
            prescriber: Prescriber::create(
                PrescriberId::fromString($entity->getPrescriberUserId()->toRfc4122()),
                $entity->getPrescriberDisplayName(),
                $entity->getPrescriberRegistrationNumber(),
                $entity->getPrescriberProfessionalTitle(),
            ),
            patient: PatientSnapshot::create(
                animalId: null !== $animalId ? $animalId->toRfc4122() : null,
                name: $entity->getPatientName(),
                species: $entity->getPatientSpecies(),
                breed: $entity->getPatientBreed(),
                birthDate: $entity->getPatientBirthDate(),
                weightKg: $this->trimDecimal($entity->getPatientWeightKg()),
                microchipNumber: $entity->getPatientMicrochipNumber(),
            ),
            owner: OwnerSnapshot::create(
                null !== $ownerClientId ? OwnerId::fromString($ownerClientId->toRfc4122()) : null,
                $entity->getOwnerName(),
                $entity->getOwnerAddressLines(),
            ),
            lines: array_map(
                fn (PrescriptionLineEntity $line): PrescriptionLine => $this->lineToDomain($line),
                $lineEntities,
            ),
            validity: $validity,
            renewals: Renewals::reconstitute($entity->getRenewalsAllowed(), $entity->getRenewalsUsed()),
            renewalHistory: array_map(
                static fn (RenewalEntity $renewal): Renewal => Renewal::create(
                    RenewalId::fromString($renewal->getId()->toRfc4122()),
                    $renewal->getRenewedAt(),
                    $renewal->getNewValidUntil(),
                    $renewal->getRenewedByUserId()?->toRfc4122(),
                ),
                $renewalEntities,
            ),
            notes: $entity->getNotes(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            cancelledAt: $entity->getCancelledAt(),
            cancellationReason: $entity->getCancellationReason(),
        );
    }

    public function toEntity(Prescription $prescription, ?PrescriptionEntity $entity = null): PrescriptionEntity
    {
        $entity ??= new PrescriptionEntity();

        $entity->setId(Uuid::fromString($prescription->id()->toString()));
        $entity->setClinicId(Uuid::fromString($prescription->clinicId()->toString()));
        $entity->setConsultationId(Uuid::fromString($prescription->consultationId()->toString()));
        $entity->setCreatedAt($prescription->createdAt());

        $this->applyMutableState($prescription, $entity);

        return $entity;
    }

    public function applyMutableState(Prescription $prescription, PrescriptionEntity $entity): void
    {
        $patient  = $prescription->patient();
        $owner    = $prescription->owner();
        $validity = $prescription->validity();
        $animalId = $patient->animalId();
        $clientId = $owner->clientId();

        $entity->setNumber($prescription->number()?->toString());
        $entity->setStatus($prescription->status());
        $entity->setPrescriberUserId(Uuid::fromString($prescription->prescriber()->userId()->toString()));
        $entity->setPrescriberDisplayName($prescription->prescriber()->displayName());
        $entity->setPrescriberRegistrationNumber($prescription->prescriber()->registrationNumber());
        $entity->setPrescriberProfessionalTitle($prescription->prescriber()->professionalTitle());
        $entity->setPatientAnimalId(null !== $animalId ? Uuid::fromString($animalId) : null);
        $entity->setPatientName($patient->name());
        $entity->setPatientSpecies($patient->species());
        $entity->setPatientBreed($patient->breed());
        $entity->setPatientBirthDate($patient->birthDate());
        $entity->setPatientWeightKg($patient->weightKg());
        $entity->setPatientMicrochipNumber($patient->microchipNumber());
        $entity->setOwnerClientId(null !== $clientId ? Uuid::fromString($clientId->toString()) : null);
        $entity->setOwnerName($owner->displayName());
        $entity->setOwnerAddressLines($owner->addressLines());
        $entity->setIssuedAt($validity?->issuedAt());
        $entity->setValidUntil($validity?->validUntil());
        $entity->setValiditySpanDays($validity?->spanDays());
        $entity->setRenewalsAllowed($prescription->renewals()->allowed());
        $entity->setRenewalsUsed($prescription->renewals()->used());
        $entity->setNotes($prescription->notes());
        $entity->setUpdatedAt($prescription->updatedAt());
        $entity->setCancelledAt($prescription->cancelledAt());
        $entity->setCancellationReason($prescription->cancellationReason());
    }

    public function lineToEntity(
        PrescriptionLine $line,
        PrescriptionId $prescriptionId,
        int $position,
    ): PrescriptionLineEntity {
        $entity    = new PrescriptionLineEntity();
        $articleId = $line->articleId();

        $entity->setId(Uuid::fromString($line->id()->toString()));
        $entity->setPrescriptionId(Uuid::fromString($prescriptionId->toString()));
        $entity->setArticleId(null !== $articleId ? Uuid::fromString($articleId) : null);
        $entity->setCode($line->code());
        $entity->setLabel($line->label());
        $entity->setPharmaceuticalForm($line->pharmaceuticalForm());
        $entity->setActiveSubstances($line->activeSubstances());
        $entity->setDose($line->dose());
        $entity->setFrequency($line->frequency());
        $entity->setDurationDays($line->durationDays());
        $entity->setRoute($line->route());
        $entity->setQuantity($line->quantity());
        $entity->setPrescriptionClass($line->prescriptionClass());
        $entity->setInstructions($line->instructions());
        $entity->setPosition($position);

        return $entity;
    }

    public function renewalToEntity(
        Renewal $renewal,
        PrescriptionId $prescriptionId,
        int $position,
    ): RenewalEntity {
        $entity  = new RenewalEntity();
        $renewer = $renewal->renewedByUserId();

        $entity->setId(Uuid::fromString($renewal->id()->toString()));
        $entity->setPrescriptionId(Uuid::fromString($prescriptionId->toString()));
        $entity->setRenewedAt($renewal->renewedAt());
        $entity->setNewValidUntil($renewal->newValidUntil());
        $entity->setRenewedByUserId(null !== $renewer ? Uuid::fromString($renewer) : null);
        $entity->setPosition($position);

        return $entity;
    }

    private function lineToDomain(PrescriptionLineEntity $entity): PrescriptionLine
    {
        $articleId = $entity->getArticleId();

        return PrescriptionLine::create(
            id: PrescriptionLineId::fromString($entity->getId()->toRfc4122()),
            articleId: null !== $articleId ? $articleId->toRfc4122() : null,
            code: $entity->getCode(),
            label: $entity->getLabel(),
            pharmaceuticalForm: $entity->getPharmaceuticalForm(),
            activeSubstances: $entity->getActiveSubstances(),
            dose: $entity->getDose(),
            frequency: $entity->getFrequency(),
            durationDays: $entity->getDurationDays(),
            route: $entity->getRoute(),
            quantity: $this->trimDecimal($entity->getQuantity()) ?? '1',
            prescriptionClass: $entity->getPrescriptionClass(),
            instructions: $entity->getInstructions(),
        );
    }

    /**
     * DECIMAL columns come back zero-padded ("2.0000"); the value objects expect
     * the shape they were created with.
     */
    private function trimDecimal(?string $value): ?string
    {
        if (null === $value || !str_contains($value, '.')) {
            return $value;
        }

        $trimmed = mb_rtrim(mb_rtrim($value, '0'), '.');

        return '' === $trimmed ? '0' : $trimmed;
    }
}
