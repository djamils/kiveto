<?php

declare(strict_types=1);

namespace App\Fixtures\AccessControl\Factory;

use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use App\AccessControl\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ClinicMembershipEntity>
 */
final class ClinicMembershipEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ClinicMembershipEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->with(['id' => Uuid::fromString($id)]);
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->with(['clinicId' => Uuid::fromString($clinicId)]);
    }

    public function withUserId(string $userId): self
    {
        return $this->with(['userId' => Uuid::fromString($userId)]);
    }

    public function withRole(ClinicMemberRole $role): self
    {
        return $this->with(['role' => $role]);
    }

    public function asVeterinary(): self
    {
        return $this->with(['role' => ClinicMemberRole::VETERINARY]);
    }

    public function asAssistantVeterinary(): self
    {
        return $this->with(['role' => ClinicMemberRole::ASSISTANT_VETERINARY]);
    }

    public function asClinicAdmin(): self
    {
        return $this->with(['role' => ClinicMemberRole::CLINIC_ADMIN]);
    }

    public function asEmployee(): self
    {
        return $this->with(['engagement' => ClinicMembershipEngagement::EMPLOYEE, 'validUntil' => null]);
    }

    public function asContractor(\DateTimeImmutable $validUntil): self
    {
        return $this->with(['engagement' => ClinicMembershipEngagement::CONTRACTOR, 'validUntil' => $validUntil]);
    }

    public function disabled(): self
    {
        return $this->with(['status' => ClinicMembershipStatus::DISABLED]);
    }

    protected function defaults(): array
    {
        $createdAt = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 year'));
        $validFrom = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-6 months'));

        return [
            'id'         => Uuid::v7(),
            'clinicId'   => Uuid::v7(),
            'userId'     => Uuid::v7(),
            'role'       => self::faker()->randomElement(ClinicMemberRole::cases()),
            'engagement' => self::faker()->randomElement(ClinicMembershipEngagement::cases()),
            'status'     => ClinicMembershipStatus::ACTIVE,
            'validFrom'  => $validFrom,
            'validUntil' => null,
            'createdAt'  => $createdAt,
        ];
    }
}
