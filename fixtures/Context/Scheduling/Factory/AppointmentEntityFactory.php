<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Factory;

use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
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

    public function withLinkedAdmissionId(?string $linkedAdmissionId): self
    {
        return $this->afterInstantiate(
            static function (AppointmentEntity $entity) use ($linkedAdmissionId): void {
                $entity->setLinkedAdmissionId(
                    null !== $linkedAdmissionId ? Uuid::fromString($linkedAdmissionId) : null
                );
            },
        );
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

    public function withOwnerAndAnimal(?string $ownerId, ?string $animalId): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity) use ($ownerId, $animalId): void {
            $entity->setOwnerId(null !== $ownerId ? Uuid::fromString($ownerId) : null);
            $entity->setAnimalId(null !== $animalId ? Uuid::fromString($animalId) : null);
        });
    }

    public function withReason(?string $reason): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity) use ($reason): void {
            $entity->setReason($reason);
        });
    }

    /**
     * An online request waiting for the clinic's confirmation (« à confirmer »).
     */
    public function pending(\DateTimeImmutable $expiresAt, ?string $bookingMotifId = null): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity) use ($expiresAt, $bookingMotifId): void {
            $entity->setStatus(AppointmentStatus::PENDING_CLINIC_CONFIRMATION);
            $entity->setSource(AppointmentSource::PORTAL);
            $entity->setPendingExpiresAt($expiresAt);
            $entity->setBookingMotifId(null !== $bookingMotifId ? Uuid::fromString($bookingMotifId) : null);
        });
    }

    /**
     * An online request the clinic did not confirm.
     */
    public function declined(): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity): void {
            $entity->setStatus(AppointmentStatus::DECLINED);
            $entity->setSource(AppointmentSource::PORTAL);
        });
    }

    public function cancelledBy(CancellationActor $actor): self
    {
        return $this->afterInstantiate(static function (AppointmentEntity $entity) use ($actor): void {
            $entity->setStatus(AppointmentStatus::CANCELLED);
            $entity->setCancelledBy($actor);
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'id'                 => Uuid::v7(),
            'clinicId'           => Uuid::v7(),
            'linkedAdmissionId'  => null,
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
