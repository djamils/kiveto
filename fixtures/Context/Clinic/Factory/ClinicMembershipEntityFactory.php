<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Clinic\Factory;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
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

    public function asManager(): self
    {
        return $this->with(['role' => ClinicMemberRole::MANAGER]);
    }

    public function asVeterinary(): self
    {
        return $this->with(['role' => ClinicMemberRole::VETERINARY])
            ->afterPersist(static function (ClinicMembershipEntity $membership): void {
                StaffProfileEntityFactory::new()
                    ->asDoctor()
                    ->with([
                        'membershipId' => $membership->getId(),
                        'createdAt'    => $membership->getCreatedAt(),
                        'updatedAt'    => $membership->getCreatedAt(),
                    ])
                    ->create()
                ;
            })
        ;
    }

    public function asVeterinaryAssistant(): self
    {
        return $this->with(['role' => ClinicMemberRole::VETERINARY_ASSISTANT])
            ->afterPersist(static function (ClinicMembershipEntity $membership): void {
                StaffProfileEntityFactory::new()
                    ->withoutCredentials()
                    ->with([
                        'membershipId' => $membership->getId(),
                        'createdAt'    => $membership->getCreatedAt(),
                        'updatedAt'    => $membership->getCreatedAt(),
                    ])
                    ->create()
                ;
            })
        ;
    }

    public function asReceptionist(): self
    {
        return $this->with(['role' => ClinicMemberRole::RECEPTIONIST]);
    }

    public function asEmployee(): self
    {
        return $this->with(['engagement' => ClinicMembershipEngagement::EMPLOYEE, 'validUntil' => null]);
    }

    public function asContractor(\DateTimeImmutable $validUntil): self
    {
        return $this->with(['engagement' => ClinicMembershipEngagement::CONTRACTOR, 'validUntil' => $validUntil]);
    }

    public function asDefault(): self
    {
        return $this->with(['isDefault' => true]);
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
            'role'       => ClinicMemberRole::RECEPTIONIST,
            'engagement' => self::faker()->randomElement(ClinicMembershipEngagement::cases()),
            'status'     => ClinicMembershipStatus::ACTIVE,
            'validFrom'  => $validFrom,
            'validUntil' => null,
            'createdAt'  => $createdAt,
            'isDefault'  => false,
        ];
    }
}
