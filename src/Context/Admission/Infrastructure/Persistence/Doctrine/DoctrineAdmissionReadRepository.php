<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Persistence\Doctrine;

use App\Context\Admission\Application\Port\AdmissionContextDto;
use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\LocationStatusValue;
use App\Context\Admission\Infrastructure\Persistence\Doctrine\Entity\AdmissionEntity;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAdmissionReadRepository implements AdmissionReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<WaitingRoomItemDto>
     */
    public function findActiveInWaitingRoom(string $clinicId): array
    {
        $clinicUuid = Uuid::fromString($clinicId);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(AdmissionEntity::class, 'a')
            ->where('a.clinicId = :clinicId')
            ->andWhere('a.status = :status')
            ->andWhere('a.locationStatusValue = :locationStatus')
            ->setParameter('clinicId', $clinicUuid, UuidType::NAME)
            ->setParameter('status', AdmissionStatus::Active)
            ->setParameter('locationStatus', LocationStatusValue::InWaitingRoom)
            ->orderBy('a.openedAt', 'ASC')
        ;

        /** @var list<AdmissionEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        if ([] === $entities) {
            return [];
        }

        // Fetch display_label_value from Patient BC in a single DBAL query
        $patientBinIds = array_map(
            static fn (AdmissionEntity $e): string => $e->getPatientId()->toBinary(),
            $entities,
        );

        $conn = $this->entityManager->getConnection();
        $rows = $conn->fetchAllKeyValue(
            'SELECT id, display_label_value FROM patient__patients WHERE id IN (?)',
            [$patientBinIds],
            [ArrayParameterType::STRING],
        );

        // Re-key by RFC 4122 UUID string for O(1) lookup
        /** @var array<string, string> $labels */
        $labels = [];
        foreach ($rows as $binId => $label) {
            \assert(\is_string($label));
            $labels[Uuid::fromBinary((string) $binId)->toRfc4122()] = $label;
        }

        $items = [];
        foreach ($entities as $entity) {
            $patientUuid = $entity->getPatientId()->toString();
            $items[]     = new WaitingRoomItemDto(
                admissionId: $entity->getId()->toString(),
                clinicId: $entity->getClinicId()->toString(),
                patientId: $patientUuid,
                displayLabel: $labels[$patientUuid] ?? $patientUuid,
                triageLevel: $entity->getTriageLevel()->value,
                locationStatus: $entity->getLocationStatusValue()->value,
                intakeChannel: $entity->getIntakeChannel()->value,
                openedAt: $entity->getOpenedAt()->format(\DateTimeInterface::ATOM),
                triageNotes: $entity->getTriageNotes(),
                isPatientIdentifiedAtOpening: $entity->isPatientIdentifiedAtOpening(),
            );
        }

        return $items;
    }

    /**
     * @return list<WaitingRoomItemDto>
     */
    public function findAllActiveForClinic(string $clinicId): array
    {
        $clinicUuid = Uuid::fromString($clinicId);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(AdmissionEntity::class, 'a')
            ->where('a.clinicId = :clinicId')
            ->andWhere('a.status = :status')
            ->setParameter('clinicId', $clinicUuid, UuidType::NAME)
            ->setParameter('status', AdmissionStatus::Active)
            ->orderBy('a.openedAt', 'ASC')
        ;

        /** @var list<AdmissionEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        if ([] === $entities) {
            return [];
        }

        $patientBinIds = array_map(
            static fn (AdmissionEntity $e): string => $e->getPatientId()->toBinary(),
            $entities,
        );

        $conn = $this->entityManager->getConnection();

        $labelRows = $conn->fetchAllAssociative(
            'SELECT BIN_TO_UUID(p.id) AS patient_id, p.display_label_value, BIN_TO_UUID(p.animal_link_id) AS animal_link_id
             FROM patient__patients p WHERE p.id IN (?)',
            [$patientBinIds],
            [ArrayParameterType::STRING],
        );

        /** @var array<string, string> $labels */
        $labels = [];
        /** @var array<string, string|null> $animalIds */
        $animalIds = [];

        foreach ($labelRows as $row) {
            \assert(\is_string($row['patient_id']));
            \assert(\is_string($row['display_label_value']));
            $labels[$row['patient_id']]    = $row['display_label_value'];
            $animalIds[$row['patient_id']] = \is_string($row['animal_link_id']) ? $row['animal_link_id'] : null;
        }

        // Fetch practitioner label from linked appointment (display-only enrichment)
        $admissionBinIds = array_map(
            static fn (AdmissionEntity $e): string => $e->getId()->toBinary(),
            $entities,
        );

        $practitionerRows = $conn->fetchAllAssociative(
            'SELECT BIN_TO_UUID(sa.linked_admission_id) AS admission_id, sp.display_name AS vet_name
             FROM scheduling__appointments sa
             JOIN clinic__clinic_memberships cm ON cm.user_id = sa.practitioner_user_id AND cm.clinic_id = UUID_TO_BIN(?)
             JOIN clinic__staff_profiles sp ON sp.membership_id = cm.id
             WHERE sa.linked_admission_id IN (?)',
            [$clinicId, $admissionBinIds],
            [\Doctrine\DBAL\ParameterType::STRING, ArrayParameterType::STRING],
        );

        /** @var array<string, string> $practitionerLabels */
        $practitionerLabels = [];
        foreach ($practitionerRows as $pr) {
            \assert(\is_string($pr['admission_id']));
            if (\is_string($pr['vet_name'])) {
                $practitionerLabels[$pr['admission_id']] = $pr['vet_name'];
            }
        }

        $items = [];
        foreach ($entities as $entity) {
            $patientUuid = $entity->getPatientId()->toString();
            $admissionId = $entity->getId()->toString();
            $items[]     = new WaitingRoomItemDto(
                admissionId: $admissionId,
                clinicId: $entity->getClinicId()->toString(),
                patientId: $patientUuid,
                displayLabel: $labels[$patientUuid] ?? $patientUuid,
                triageLevel: $entity->getTriageLevel()->value,
                locationStatus: $entity->getLocationStatusValue()->value,
                intakeChannel: $entity->getIntakeChannel()->value,
                openedAt: $entity->getOpenedAt()->format(\DateTimeInterface::ATOM),
                triageNotes: $entity->getTriageNotes(),
                isPatientIdentifiedAtOpening: $entity->isPatientIdentifiedAtOpening(),
                knownAnimalId: $animalIds[$patientUuid] ?? null,
                practitionerLabel: $practitionerLabels[$admissionId] ?? null,
            );
        }

        return $items;
    }

    public function getAdmissionContext(string $admissionId): AdmissionContextDto
    {
        $admissionUuid = Uuid::fromString($admissionId);
        $repository    = $this->entityManager->getRepository(AdmissionEntity::class);

        /** @var AdmissionEntity|null $entity */
        $entity = $repository->find($admissionUuid);

        if (null === $entity) {
            throw new \RuntimeException(\sprintf('Admission not found: %s', $admissionId));
        }

        return new AdmissionContextDto(
            patientId: $entity->getPatientId()->toString(),
            clinicId: $entity->getClinicId()->toString(),
        );
    }
}
