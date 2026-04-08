<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Factory;

use App\Context\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomEntryOrigin;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\WaitingRoomEntryEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<WaitingRoomEntryEntity>
 */
final class WaitingRoomEntryEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return WaitingRoomEntryEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(static function (WaitingRoomEntryEntity $entity) use ($id): void {
            $entity->setId(Uuid::fromString($id));
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(static function (WaitingRoomEntryEntity $entity) use ($clinicId): void {
            $entity->setClinicId(Uuid::fromString($clinicId));
        });
    }

    public function withLinkedAppointmentId(?string $appointmentId): self
    {
        return $this->afterInstantiate(static function (WaitingRoomEntryEntity $entity) use ($appointmentId): void {
            $entity->setLinkedAppointmentId(null !== $appointmentId ? Uuid::fromString($appointmentId) : null);
        });
    }

    public function withOwnerId(?string $ownerId): self
    {
        return $this->afterInstantiate(static function (WaitingRoomEntryEntity $entity) use ($ownerId): void {
            $entity->setOwnerId(null !== $ownerId ? Uuid::fromString($ownerId) : null);
        });
    }

    public function withAnimalId(?string $animalId): self
    {
        return $this->afterInstantiate(static function (WaitingRoomEntryEntity $entity) use ($animalId): void {
            $entity->setAnimalId(null !== $animalId ? Uuid::fromString($animalId) : null);
        });
    }

    public function withStatus(WaitingRoomEntryStatus $status): self
    {
        return $this->afterInstantiate(static function (WaitingRoomEntryEntity $entity) use ($status): void {
            $entity->setStatus($status);
        });
    }

    public function withOrigin(WaitingRoomEntryOrigin $origin): self
    {
        return $this->afterInstantiate(static function (WaitingRoomEntryEntity $entity) use ($origin): void {
            $entity->setOrigin($origin);
        });
    }

    public function withArrivalMode(WaitingRoomArrivalMode $mode): self
    {
        return $this->afterInstantiate(static function (WaitingRoomEntryEntity $entity) use ($mode): void {
            $entity->setArrivalMode($mode);
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'id'                     => Uuid::v7(),
            'clinicId'               => Uuid::v7(),
            'origin'                 => WaitingRoomEntryOrigin::WALK_IN,
            'arrivalMode'            => WaitingRoomArrivalMode::STANDARD,
            'linkedAppointmentId'    => null,
            'ownerId'                => null,
            'animalId'               => null,
            'foundAnimalDescription' => null,
            'priority'               => 0,
            'triageNotes'            => null,
            'status'                 => WaitingRoomEntryStatus::WAITING,
            'arrivedAtUtc'           => new \DateTimeImmutable(),
            'calledAtUtc'            => null,
            'serviceStartedAtUtc'    => null,
            'closedAtUtc'            => null,
            'calledByUserId'         => null,
            'serviceStartedByUserId' => null,
            'closedByUserId'         => null,
        ];
    }
}
