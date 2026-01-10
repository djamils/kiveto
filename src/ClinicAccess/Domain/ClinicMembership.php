<?php

declare(strict_types=1);

namespace App\ClinicAccess\Domain;

use App\ClinicAccess\Domain\Event\ClinicMembershipCreated;
use App\ClinicAccess\Domain\Event\ClinicMembershipDisabled;
use App\ClinicAccess\Domain\Event\ClinicMembershipEnabled;
use App\ClinicAccess\Domain\Event\ClinicMembershipEngagementChanged;
use App\ClinicAccess\Domain\Event\ClinicMembershipRoleChanged;
use App\ClinicAccess\Domain\Event\ClinicMembershipValidityChanged;
use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipStatus;
use App\ClinicAccess\Domain\ValueObject\MembershipId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class ClinicMembership extends AggregateRoot
{
    private MembershipId $id;
    private ClinicId $clinicId;
    private UserId $userId;
    private ClinicMemberRole $role;
    private ClinicMembershipEngagement $engagement;
    private ClinicMembershipStatus $status;
    private \DateTimeImmutable $validFrom;
    private ?\DateTimeImmutable $validUntil;
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function create(
        MembershipId $id,
        ClinicId $clinicId,
        UserId $userId,
        ClinicMemberRole $role,
        ClinicMembershipEngagement $engagement,
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
        \DateTimeImmutable $createdAt,
    ): self {
        self::validateValidityWindow($validFrom, $validUntil);

        $membership                  = new self();
        $membership->id              = $id;
        $membership->clinicId        = $clinicId;
        $membership->userId          = $userId;
        $membership->role            = $role;
        $membership->engagement      = $engagement;
        $membership->status          = ClinicMembershipStatus::ACTIVE;
        $membership->validFrom       = $validFrom;
        $membership->validUntil      = $validUntil;
        $membership->createdAt       = $createdAt;

        $membership->recordDomainEvent(new ClinicMembershipCreated(
            membershipId: $id->toString(),
            clinicId: $clinicId->toString(),
            userId: $userId->toString(),
            role: $role->value,
            engagement: $engagement->value,
            validFrom: $validFrom->format(\DateTimeInterface::ATOM),
            validUntil: $validUntil?->format(\DateTimeInterface::ATOM),
        ));

        return $membership;
    }

    public static function reconstitute(
        MembershipId $id,
        ClinicId $clinicId,
        UserId $userId,
        ClinicMemberRole $role,
        ClinicMembershipEngagement $engagement,
        ClinicMembershipStatus $status,
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
        \DateTimeImmutable $createdAt,
    ): self {
        $membership                  = new self();
        $membership->id              = $id;
        $membership->clinicId        = $clinicId;
        $membership->userId          = $userId;
        $membership->role            = $role;
        $membership->engagement      = $engagement;
        $membership->status          = $status;
        $membership->validFrom       = $validFrom;
        $membership->validUntil      = $validUntil;
        $membership->createdAt       = $createdAt;

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

    public function changeValidity(
        \DateTimeImmutable $validFrom,
        ?\DateTimeImmutable $validUntil,
    ): void {
        self::validateValidityWindow($validFrom, $validUntil);

        if ($validFrom == $this->validFrom && $validUntil == $this->validUntil) {
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

    public function isEffectiveAt(\DateTimeImmutable $nowUtc): bool
    {
        if (ClinicMembershipStatus::DISABLED === $this->status) {
            return false;
        }

        if ($nowUtc < $this->validFrom) {
            return false;
        }

        if (null !== $this->validUntil && $nowUtc > $this->validUntil) {
            return false;
        }

        return true;
    }

    public function id(): MembershipId
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
