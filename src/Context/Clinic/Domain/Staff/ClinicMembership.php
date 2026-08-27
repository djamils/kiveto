<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipAdministrationChanged;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipCreated;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDefaultChanged;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDisabled;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipEnabled;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipEngagementChanged;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipRoleChanged;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipValidityChanged;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class ClinicMembership extends AggregateRoot
{
    private ClinicMembershipId $id;
    private ClinicId $clinicId;
    private UserId $userId;
    private ClinicMemberRole $role;
    private ClinicMembershipEngagement $engagement;
    private ClinicMembershipStatus $status;
    private \DateTimeImmutable $validFrom;
    private ?\DateTimeImmutable $validUntil;
    private \DateTimeImmutable $createdAt;
    private bool $isDefault;
    private bool $isClinicAdministrator;

    private function __construct()
    {
    }

    public static function create(
        ClinicMembershipId $id,
        ClinicId $clinicId,
        UserId $userId,
        ClinicMemberRole $role,
        ClinicMembershipEngagement $engagement,
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
        \DateTimeImmutable $createdAt,
        bool $isClinicAdministrator,
    ): self {
        self::validateValidityWindow($validFrom, $validUntil);

        $membership                        = new self();
        $membership->id                    = $id;
        $membership->clinicId              = $clinicId;
        $membership->userId                = $userId;
        $membership->role                  = $role;
        $membership->engagement            = $engagement;
        $membership->status                = ClinicMembershipStatus::ACTIVE;
        $membership->validFrom             = $validFrom;
        $membership->validUntil            = $validUntil;
        $membership->createdAt             = $createdAt;
        $membership->isDefault             = false;
        $membership->isClinicAdministrator = $isClinicAdministrator;

        $membership->recordDomainEvent(new ClinicMembershipCreated(
            membershipId: $id->toString(),
            clinicId: $clinicId->toString(),
            userId: $userId->toString(),
            role: $role->value,
            engagement: $engagement->value,
            validFrom: $validFrom->format(\DateTimeInterface::ATOM),
            validUntil: $validUntil?->format(\DateTimeInterface::ATOM),
            isClinicAdministrator: $isClinicAdministrator,
        ));

        return $membership;
    }

    public static function reconstitute(
        ClinicMembershipId $id,
        ClinicId $clinicId,
        UserId $userId,
        ClinicMemberRole $role,
        ClinicMembershipEngagement $engagement,
        ClinicMembershipStatus $status,
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
        \DateTimeImmutable $createdAt,
        bool $isDefault,
        bool $isClinicAdministrator,
    ): self {
        $membership                        = new self();
        $membership->id                    = $id;
        $membership->clinicId              = $clinicId;
        $membership->userId                = $userId;
        $membership->role                  = $role;
        $membership->engagement            = $engagement;
        $membership->status                = $status;
        $membership->validFrom             = $validFrom;
        $membership->validUntil            = $validUntil;
        $membership->createdAt             = $createdAt;
        $membership->isDefault             = $isDefault;
        $membership->isClinicAdministrator = $isClinicAdministrator;

        return $membership;
    }

    public function disable(): void
    {
        if (ClinicMembershipStatus::DISABLED === $this->status) {
            return;
        }

        $this->status = ClinicMembershipStatus::DISABLED;

        $this->recordDomainEvent(new ClinicMembershipDisabled(
            membershipId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            userId: $this->userId->toString(),
        ));
    }

    public function enable(): void
    {
        if (ClinicMembershipStatus::ACTIVE === $this->status) {
            return;
        }

        $this->status = ClinicMembershipStatus::ACTIVE;

        $this->recordDomainEvent(new ClinicMembershipEnabled(
            membershipId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            userId: $this->userId->toString(),
            role: $this->role->value,
        ));
    }

    public function changeRole(ClinicMemberRole $newRole): void
    {
        if ($newRole === $this->role) {
            return;
        }

        $this->role = $newRole;

        $this->recordDomainEvent(new ClinicMembershipRoleChanged(
            membershipId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            userId: $this->userId->toString(),
            newRole: $newRole->value,
        ));
    }

    public function changeEngagement(ClinicMembershipEngagement $newEngagement): void
    {
        if ($newEngagement === $this->engagement) {
            return;
        }

        $this->engagement = $newEngagement;

        $this->recordDomainEvent(new ClinicMembershipEngagementChanged(
            membershipId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            userId: $this->userId->toString(),
            newEngagement: $newEngagement->value,
        ));
    }

    public function changeValidityWindow(
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
    ): void {
        self::validateValidityWindow($validFrom, $validUntil);

        if ($validFrom === $this->validFrom && $validUntil === $this->validUntil) {
            return;
        }

        $this->validFrom  = $validFrom;
        $this->validUntil = $validUntil;

        $this->recordDomainEvent(new ClinicMembershipValidityChanged(
            membershipId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            userId: $this->userId->toString(),
            validFrom: $validFrom->format(\DateTimeInterface::ATOM),
            validUntil: $validUntil?->format(\DateTimeInterface::ATOM),
        ));
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function isClinicAdministrator(): bool
    {
        return $this->isClinicAdministrator;
    }

    public function grantAdministration(): void
    {
        if ($this->isClinicAdministrator) {
            return;
        }

        $this->isClinicAdministrator = true;

        $this->recordDomainEvent(new ClinicMembershipAdministrationChanged(
            membershipId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            userId: $this->userId->toString(),
            isClinicAdministrator: true,
        ));
    }

    public function revokeAdministration(): void
    {
        if (!$this->isClinicAdministrator) {
            return;
        }

        $this->isClinicAdministrator = false;

        $this->recordDomainEvent(new ClinicMembershipAdministrationChanged(
            membershipId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            userId: $this->userId->toString(),
            isClinicAdministrator: false,
        ));
    }

    /**
     * Wearing the hat only counts while the membership is in force: an expired
     * administrator keeps the flag on their row but opens nothing.
     */
    public function isEffectiveAdministratorAt(\DateTimeImmutable $now): bool
    {
        return $this->isClinicAdministrator && $this->isEffectiveAt($now);
    }

    /**
     * What this membership may do: the clinical role's permissions, plus the
     * administrator's when it wears the hat. Role and hat are independent axes,
     * so the answer is their union rather than either one alone.
     *
     * @return list<ClinicPermission>
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach (StaffRolePermissionMap::permissionsFor($this->role) as $permission) {
            $permissions[$permission->value] = $permission;
        }

        if ($this->isClinicAdministrator) {
            foreach (StaffRolePermissionMap::administratorPermissions() as $permission) {
                $permissions[$permission->value] = $permission;
            }
        }

        return array_values($permissions);
    }

    public function setAsDefault(): void
    {
        if ($this->isDefault) {
            return;
        }

        $this->isDefault = true;

        $this->recordDomainEvent(new ClinicMembershipDefaultChanged(
            membershipId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            userId: $this->userId->toString(),
            isDefault: true,
        ));
    }

    public function clearDefault(): void
    {
        if (!$this->isDefault) {
            return;
        }

        $this->isDefault = false;

        $this->recordDomainEvent(new ClinicMembershipDefaultChanged(
            membershipId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            userId: $this->userId->toString(),
            isDefault: false,
        ));
    }

    public function isEffectiveAt(\DateTimeImmutable $now): bool
    {
        if (ClinicMembershipStatus::DISABLED === $this->status) {
            return false;
        }

        if ($now < $this->validFrom) {
            return false;
        }

        if (null !== $this->validUntil && $now > $this->validUntil) {
            return false;
        }

        return true;
    }

    public function id(): ClinicMembershipId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function role(): ClinicMemberRole
    {
        return $this->role;
    }

    public function engagement(): ClinicMembershipEngagement
    {
        return $this->engagement;
    }

    public function status(): ClinicMembershipStatus
    {
        return $this->status;
    }

    public function validFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function validateValidityWindow(
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
    ): void {
        if (null !== $validUntil && $validFrom > $validUntil) {
            throw new \DomainException('validFrom must be before or equal to validUntil.');
        }
    }
}
