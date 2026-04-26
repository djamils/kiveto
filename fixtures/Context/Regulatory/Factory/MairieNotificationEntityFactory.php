<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Regulatory\Factory;

use App\Context\Regulatory\Domain\ValueObject\MairieNotificationStatus;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\MairieNotificationEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<MairieNotificationEntity>
 */
final class MairieNotificationEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return MairieNotificationEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(function (MairieNotificationEntity $entity) use ($id): void {
            $entity->setId(Uuid::fromString($id));
        });
    }

    public function withAdmissionId(string $admissionId): self
    {
        return $this->afterInstantiate(function (MairieNotificationEntity $entity) use ($admissionId): void {
            $entity->setAdmissionId(Uuid::fromString($admissionId));
        });
    }

    public function withPatientId(string $patientId): self
    {
        return $this->afterInstantiate(function (MairieNotificationEntity $entity) use ($patientId): void {
            $entity->setPatientId(Uuid::fromString($patientId));
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(function (MairieNotificationEntity $entity) use ($clinicId): void {
            $entity->setClinicId(Uuid::fromString($clinicId));
        });
    }

    public function withStatus(MairieNotificationStatus $status): self
    {
        return $this->afterInstantiate(function (MairieNotificationEntity $entity) use ($status): void {
            $entity->setStatus($status);
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'id'          => Uuid::v7(),
            'admissionId' => Uuid::v7(),
            'patientId'   => Uuid::v7(),
            'clinicId'    => Uuid::v7(),
            'status'      => MairieNotificationStatus::Pending,
            // 8-day legal deadline after admission opening
            'deadline'  => new \DateTimeImmutable('+8 days'),
            'version'   => 1,
            'createdAt' => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-3 months')
            ),
            'updatedAt' => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-1 month')
            ),
        ];
    }
}
