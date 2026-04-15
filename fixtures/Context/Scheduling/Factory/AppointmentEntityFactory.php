<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Factory;

use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<AppointmentEntity>
 */
final class AppointmentEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return AppointmentEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity) use ($id): void {
            $entity->setId(Uuid::fromString($id));
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity) use ($clinicId): void {
            $entity->setClinicId(Uuid::fromString($clinicId));
        });
    }

    public function withOwnerId(?string $ownerId): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity) use ($ownerId): void {
            $entity->setOwnerId(null !== $ownerId ? Uuid::fromString($ownerId) : null);
        });
    }

    public function withAnimalId(?string $animalId): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity) use ($animalId): void {
            $entity->setAnimalId(null !== $animalId ? Uuid::fromString($animalId) : null);
        });
    }

    public function withPractitionerUserId(string $practitionerUserId): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity) use ($practitionerUserId): void {
            $entity->setPractitionerUserId(Uuid::fromString($practitionerUserId));
        });
    }

    public function startingAt(\DateTimeImmutable $startsAtUtc, int $durationMinutes = 30): self
    {
        return $this->afterInstantiate(
            static function (AppointmentEntity $entity) use ($startsAtUtc, $durationMinutes): void {
                $entity->setStartsAtUtc($startsAtUtc);
                $entity->setDurationMinutes($durationMinutes);
            },
        );
    }

    public function withStatus(AppointmentStatus $status): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity) use ($status): void {
            $entity->setStatus($status);
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'id'                 => Uuid::v7(),
            'clinicId'           => Uuid::v7(),
            'ownerId'            => null,
            'animalId'           => null,
            'practitionerUserId' => Uuid::v7(),
            'startsAtUtc'        => new \DateTimeImmutable('+1 day 09:00:00'),
            'durationMinutes'    => 30,
            'status'             => AppointmentStatus::PLANNED,
            'reason'             => null,
            'notes'              => null,
            'serviceStartedAt'   => null,
            'createdAt'          => new \DateTimeImmutable(),
            'updatedAt'          => new \DateTimeImmutable(),
        ];
    }
}
