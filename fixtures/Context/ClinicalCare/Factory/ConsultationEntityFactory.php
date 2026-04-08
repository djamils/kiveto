<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClinicalCare\Factory;

use App\Context\ClinicalCare\Domain\ValueObject\ConsultationStatus;
use App\Context\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ConsultationEntity>
 */
final class ConsultationEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ConsultationEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($id): void {
            $entity->setId(Uuid::fromString($id)->toBinary());
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($clinicId): void {
            $entity->setClinicId(Uuid::fromString($clinicId)->toBinary());
        });
    }

    public function withAppointmentId(?string $appointmentId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($appointmentId): void {
            $entity->setAppointmentId(null !== $appointmentId ? Uuid::fromString($appointmentId)->toBinary() : null);
        });
    }

    public function withWaitingRoomEntryId(?string $entryId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($entryId): void {
            $entity->setWaitingRoomEntryId(null !== $entryId ? Uuid::fromString($entryId)->toBinary() : null);
        });
    }

    public function withOwnerId(?string $ownerId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($ownerId): void {
            $entity->setOwnerId(null !== $ownerId ? Uuid::fromString($ownerId)->toBinary() : null);
        });
    }

    public function withAnimalId(?string $animalId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($animalId): void {
            $entity->setAnimalId(null !== $animalId ? Uuid::fromString($animalId)->toBinary() : null);
        });
    }

    public function withPractitionerUserId(string $userId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($userId): void {
            $entity->setPractitionerUserId(Uuid::fromString($userId)->toBinary());
        });
    }

    public function withStatus(ConsultationStatus $status): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($status): void {
            $entity->setStatus($status->value);
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'id'                 => Uuid::v7()->toBinary(),
            'clinicId'           => Uuid::v7()->toBinary(),
            'appointmentId'      => null,
            'waitingRoomEntryId' => null,
            'ownerId'            => null,
            'animalId'           => null,
            'practitionerUserId' => Uuid::v7()->toBinary(),
            'status'             => ConsultationStatus::OPEN->value,
            'chiefComplaint'     => null,
            'summary'            => null,
            'weightKg'           => null,
            'temperatureC'       => null,
            'startedAtUtc'       => new \DateTimeImmutable(),
            'closedAtUtc'        => null,
            'createdAtUtc'       => new \DateTimeImmutable(),
            'updatedAtUtc'       => new \DateTimeImmutable(),
        ];
    }
}
