<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Regulatory\Factory;

use App\Context\Regulatory\Domain\ValueObject\AuthorityNotificationStatus;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\AuthorityNotificationEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<AuthorityNotificationEntity>
 */
final class AuthorityNotificationEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return AuthorityNotificationEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(function (AuthorityNotificationEntity $entity) use ($id): void {
            $entity->setId(Uuid::fromString($id));
        });
    }

    public function withAdmissionId(string $admissionId): self
    {
        return $this->afterInstantiate(function (AuthorityNotificationEntity $entity) use ($admissionId): void {
            $entity->setAdmissionId(Uuid::fromString($admissionId));
        });
    }

    public function withPatientId(string $patientId): self
    {
        return $this->afterInstantiate(function (AuthorityNotificationEntity $entity) use ($patientId): void {
            $entity->setPatientId(Uuid::fromString($patientId));
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(function (AuthorityNotificationEntity $entity) use ($clinicId): void {
            $entity->setClinicId(Uuid::fromString($clinicId));
        });
    }

    public function withStatus(AuthorityNotificationStatus $status): self
    {
        return $this->afterInstantiate(function (AuthorityNotificationEntity $entity) use ($status): void {
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
            'status'      => AuthorityNotificationStatus::Pending,
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
