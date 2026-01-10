<?php

declare(strict_types=1);

namespace App\Clinic\Domain;

use App\Clinic\Domain\Event\ClinicActivated;
use App\Clinic\Domain\Event\ClinicClosed;
use App\Clinic\Domain\Event\ClinicCreated;
use App\Clinic\Domain\Event\ClinicLocaleChanged;
use App\Clinic\Domain\Event\ClinicRenamed;
use App\Clinic\Domain\Event\ClinicSlugChanged;
use App\Clinic\Domain\Event\ClinicSuspended;
use App\Clinic\Domain\Event\ClinicTimeZoneChanged;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Clinic\Domain\ValueObject\LocaleCode;
use App\Clinic\Domain\ValueObject\TimeZone;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class Clinic extends AggregateRoot
{
    private ClinicId $id;
    private ?ClinicGroupId $clinicGroupId;
    private ClinicSlug $slug;
    private string $name;
    private ClinicStatus $status;
    private TimeZone $timeZone;
    private LocaleCode $locale;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        ClinicId $id,
        string $name,
        ClinicSlug $slug,
        TimeZone $timeZone,
        LocaleCode $locale,
        \DateTimeImmutable $createdAt,
        ?ClinicGroupId $clinicGroupId = null,
    ): self {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Clinic name cannot be empty.');
        }

        $clinic                = new self();
        $clinic->id            = $id;
        $clinic->name          = $name;
        $clinic->slug          = $slug;
        $clinic->timeZone      = $timeZone;
        $clinic->locale        = $locale;
        $clinic->clinicGroupId = $clinicGroupId;
        $clinic->status        = ClinicStatus::ACTIVE;
        $clinic->createdAt     = $createdAt;
        $clinic->updatedAt     = $createdAt;

        $clinic->recordDomainEvent(new ClinicCreated(
            clinicId: $id->toString(),
            name: $name,
            slug: $slug->toString(),
            timeZone: $timeZone->toString(),
            locale: $locale->toString(),
            clinicGroupId: $clinicGroupId?->toString(),
        ));

        return $clinic;
    }

    public static function reconstitute(
        ClinicId $id,
        string $name,
        ClinicSlug $slug,
        TimeZone $timeZone,
        LocaleCode $locale,
        ClinicStatus $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        ?ClinicGroupId $clinicGroupId = null,
    ): self {
        $clinic                = new self();
        $clinic->id            = $id;
        $clinic->name          = $name;
        $clinic->slug          = $slug;
        $clinic->timeZone      = $timeZone;
        $clinic->locale        = $locale;
        $clinic->status        = $status;
        $clinic->clinicGroupId = $clinicGroupId;
        $clinic->createdAt     = $createdAt;
        $clinic->updatedAt     = $updatedAt;

        return $clinic;
    }

    public function rename(string $newName, \DateTimeImmutable $updatedAt): void
    {
        if ('' === trim($newName)) {
            throw new \InvalidArgumentException('Clinic name cannot be empty.');
        }

        if ($newName === $this->name) {
            return;
        }

        $this->name      = $newName;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ClinicRenamed(
            clinicId: $this->id->toString(),
            newName: $newName,
        ));
    }

    public function changeSlug(ClinicSlug $newSlug, \DateTimeImmutable $updatedAt): void
    {
        if ($newSlug->equals($this->slug)) {
            return;
        }

        $this->slug      = $newSlug;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ClinicSlugChanged(
            clinicId: $this->id->toString(),
            newSlug: $newSlug->toString(),
        ));
    }

    public function changeTimeZone(TimeZone $newTimeZone, \DateTimeImmutable $updatedAt): void
    {
        if ($newTimeZone->equals($this->timeZone)) {
            return;
        }

        $this->timeZone  = $newTimeZone;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ClinicTimeZoneChanged(
            clinicId: $this->id->toString(),
            newTimeZone: $newTimeZone->toString(),
        ));
    }

    public function changeLocale(LocaleCode $newLocale, \DateTimeImmutable $updatedAt): void
    {
        if ($newLocale->equals($this->locale)) {
            return;
        }

        $this->locale    = $newLocale;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ClinicLocaleChanged(
            clinicId: $this->id->toString(),
            newLocale: $newLocale->toString(),
        ));
    }

    public function suspend(\DateTimeImmutable $updatedAt): void
    {
        if (ClinicStatus::SUSPENDED === $this->status) {
            return;
        }

        $this->status    = ClinicStatus::SUSPENDED;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ClinicSuspended(
            clinicId: $this->id->toString(),
        ));
    }

    public function activate(\DateTimeImmutable $updatedAt): void
    {
        if (ClinicStatus::ACTIVE === $this->status) {
            return;
        }

        if (ClinicStatus::CLOSED === $this->status) {
            throw new \DomainException('Cannot activate a closed clinic.');
        }

        $this->status    = ClinicStatus::ACTIVE;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ClinicActivated(
            clinicId: $this->id->toString(),
        ));
    }

    public function close(\DateTimeImmutable $updatedAt): void
    {
        if (ClinicStatus::CLOSED === $this->status) {
            return;
        }

        $this->status    = ClinicStatus::CLOSED;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new ClinicClosed(
            clinicId: $this->id->toString(),
        ));
    }

    public function id(): ClinicId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): ClinicSlug
    {
        return $this->slug;
    }

    public function timeZone(): TimeZone
    {
        return $this->timeZone;
    }

    public function locale(): LocaleCode
    {
        return $this->locale;
    }

    public function status(): ClinicStatus
    {
        return $this->status;
    }

    public function clinicGroupId(): ?ClinicGroupId
    {
        return $this->clinicGroupId;
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
