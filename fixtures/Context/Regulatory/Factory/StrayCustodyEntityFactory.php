<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Regulatory\Factory;

use App\Context\Regulatory\Domain\ValueObject\StrayCustodyStatus;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\StrayCustodyEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<StrayCustodyEntity>
 */
final class StrayCustodyEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return StrayCustodyEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(function (StrayCustodyEntity $entity) use ($id): void {
            $entity->setId(Uuid::fromString($id));
        });
    }

    public function withAdmissionId(string $admissionId): self
    {
        return $this->afterInstantiate(function (StrayCustodyEntity $entity) use ($admissionId): void {
            $entity->setAdmissionId(Uuid::fromString($admissionId));
        });
    }

    public function withPatientId(string $patientId): self
    {
        return $this->afterInstantiate(function (StrayCustodyEntity $entity) use ($patientId): void {
            $entity->setPatientId(Uuid::fromString($patientId));
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(function (StrayCustodyEntity $entity) use ($clinicId): void {
            $entity->setClinicId(Uuid::fromString($clinicId));
        });
    }

    public function withStatus(StrayCustodyStatus $status): self
    {
        return $this->afterInstantiate(function (StrayCustodyEntity $entity) use ($status): void {
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
            'status'      => StrayCustodyStatus::Active,
            // 8-day legal custody deadline
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
