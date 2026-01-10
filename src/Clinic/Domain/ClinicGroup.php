<?php

declare(strict_types=1);

namespace App\Clinic\Domain;

use App\Clinic\Domain\Event\ClinicGroupActivated;
use App\Clinic\Domain\Event\ClinicGroupCreated;
use App\Clinic\Domain\Event\ClinicGroupRenamed;
use App\Clinic\Domain\Event\ClinicGroupSuspended;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class ClinicGroup extends AggregateRoot
{
    private ClinicGroupId $id;
    private string $name;
    private ClinicGroupStatus $status;
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function create(
        ClinicGroupId $id,
        string $name,
        \DateTimeImmutable $createdAt,
    ): self {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Clinic group name cannot be empty.');
        }

        $group            = new self();
        $group->id        = $id;
        $group->name      = $name;
        $group->status    = ClinicGroupStatus::ACTIVE;
        $group->createdAt = $createdAt;

        $group->recordDomainEvent(new ClinicGroupCreated(
            clinicGroupId: $id->toString(),
            name: $name,
        ));

        return $group;
    }

    public static function reconstitute(
        ClinicGroupId $id,
        string $name,
        ClinicGroupStatus $status,
        \DateTimeImmutable $createdAt,
    ): self {
        $group            = new self();
        $group->id        = $id;
        $group->name      = $name;
        $group->status    = $status;
        $group->createdAt = $createdAt;

        return $group;
    }

    public function rename(string $newName): void
    {
        if ('' === trim($newName)) {
            throw new \InvalidArgumentException('Clinic group name cannot be empty.');
        }

        if ($newName === $this->name) {
            return;
        }

        $this->name = $newName;

        $this->recordDomainEvent(new ClinicGroupRenamed(
            clinicGroupId: $this->id->toString(),
            newName: $newName,
        ));
    }

    public function suspend(): void
    {
        if (ClinicGroupStatus::SUSPENDED === $this->status) {
            return;
        }

        $this->status = ClinicGroupStatus::SUSPENDED;

        $this->recordDomainEvent(new ClinicGroupSuspended(
            clinicGroupId: $this->id->toString(),
        ));
    }

    public function activate(): void
    {
        if (ClinicGroupStatus::ACTIVE === $this->status) {
            return;
        }

        $this->status = ClinicGroupStatus::ACTIVE;

        $this->recordDomainEvent(new ClinicGroupActivated(
            clinicGroupId: $this->id->toString(),
        ));
    }

    public function id(): ClinicGroupId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): ClinicGroupStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
