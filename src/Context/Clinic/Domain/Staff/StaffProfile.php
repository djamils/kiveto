<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\PhoneNumber;

final class StaffProfile extends AggregateRoot
{
    private StaffProfileId $id;
    private ClinicMembershipId $membershipId;
    private string $firstName;
    private string $lastName;
    private DisplayName $displayName;
    private ?PhoneNumber $phoneNumber;
    private HexColor $agendaColor;
    private int $sortOrder;
    private bool $isVisibleInAgenda;
    private ?VeterinaryCredentials $credentials;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        StaffProfileId $id,
        ClinicMembershipId $membershipId,
        string $firstName,
        string $lastName,
        DisplayName $displayName,
        ?PhoneNumber $phoneNumber,
        HexColor $agendaColor,
        int $sortOrder,
        bool $isVisibleInAgenda,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $profile                    = new self();
        $profile->id                = $id;
        $profile->membershipId      = $membershipId;
        $profile->firstName         = $firstName;
        $profile->lastName          = $lastName;
        $profile->displayName       = $displayName;
        $profile->phoneNumber       = $phoneNumber;
        $profile->agendaColor       = $agendaColor;
        $profile->sortOrder         = $sortOrder;
        $profile->isVisibleInAgenda = $isVisibleInAgenda;
        $profile->credentials       = null;
        $profile->createdAt         = $createdAt;
        $profile->updatedAt         = $updatedAt;

        return $profile;
    }

    public static function reconstitute(
        StaffProfileId $id,
        ClinicMembershipId $membershipId,
        string $firstName,
        string $lastName,
        DisplayName $displayName,
        ?PhoneNumber $phoneNumber,
        HexColor $agendaColor,
        int $sortOrder,
        bool $isVisibleInAgenda,
        ?VeterinaryCredentials $credentials,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $profile                    = new self();
        $profile->id                = $id;
        $profile->membershipId      = $membershipId;
        $profile->firstName         = $firstName;
        $profile->lastName          = $lastName;
        $profile->displayName       = $displayName;
        $profile->phoneNumber       = $phoneNumber;
        $profile->agendaColor       = $agendaColor;
        $profile->sortOrder         = $sortOrder;
        $profile->isVisibleInAgenda = $isVisibleInAgenda;
        $profile->credentials       = $credentials;
        $profile->createdAt         = $createdAt;
        $profile->updatedAt         = $updatedAt;

        return $profile;
    }

    public function rename(
        string $firstName,
        string $lastName,
        DisplayName $displayName,
        \DateTimeImmutable $now,
    ): void {
        $this->firstName   = $firstName;
        $this->lastName    = $lastName;
        $this->displayName = $displayName;
        $this->updatedAt   = $now;
    }

    public function changePhoneNumber(?PhoneNumber $phoneNumber, \DateTimeImmutable $now): void
    {
        $this->phoneNumber = $phoneNumber;
        $this->updatedAt   = $now;
    }

    public function updateAgendaPreferences(
        HexColor $agendaColor,
        int $sortOrder,
        bool $isVisibleInAgenda,
        \DateTimeImmutable $now,
    ): void {
        $this->agendaColor       = $agendaColor;
        $this->sortOrder         = $sortOrder;
        $this->isVisibleInAgenda = $isVisibleInAgenda;
        $this->updatedAt         = $now;
    }

    public function registerVeterinaryCredentials(
        VeterinaryCredentials $credentials,
        \DateTimeImmutable $now,
    ): void {
        $this->credentials = $credentials;
        $this->updatedAt   = $now;
    }

    public function clearVeterinaryCredentials(\DateTimeImmutable $now): void
    {
        $this->credentials = null;
        $this->updatedAt   = $now;
    }

    public function hasVeterinaryCredentials(): bool
    {
        return null !== $this->credentials;
    }

    public function id(): StaffProfileId
    {
        return $this->id;
    }

    public function membershipId(): ClinicMembershipId
    {
        return $this->membershipId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function displayName(): DisplayName
    {
        return $this->displayName;
    }

    public function phoneNumber(): ?PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function agendaColor(): HexColor
    {
        return $this->agendaColor;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isVisibleInAgenda(): bool
    {
        return $this->isVisibleInAgenda;
    }

    public function credentials(): ?VeterinaryCredentials
    {
        return $this->credentials;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
