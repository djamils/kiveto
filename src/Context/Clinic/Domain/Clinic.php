<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain;

use App\Context\Clinic\Domain\Event\ClinicActivated;
use App\Context\Clinic\Domain\Event\ClinicClosed;
use App\Context\Clinic\Domain\Event\ClinicCreated;
use App\Context\Clinic\Domain\Event\ClinicLocaleChanged;
use App\Context\Clinic\Domain\Event\ClinicRenamed;
use App\Context\Clinic\Domain\Event\ClinicSlugChanged;
use App\Context\Clinic\Domain\Event\ClinicSuspended;
use App\Context\Clinic\Domain\Event\ClinicTimeZoneChanged;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;

final class Clinic extends AggregateRoot
{
    private ClinicId $id;
    private ?ClinicGroupId $clinicGroupId;
    private ClinicSlug $slug;
    private string $name;
    private ClinicStatus $status;
    private TimeZone $timeZone;
    private Locale $locale;
    private CountryCode $countryCode;
    private ?string $jurisdictionCode;
    private CurrencyCode $currencyCode;
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
        Locale $locale,
        CountryCode $countryCode,
        CurrencyCode $currencyCode,
        \DateTimeImmutable $createdAt,
        ?ClinicGroupId $clinicGroupId = null,
        ?string $jurisdictionCode = null,
    ): self {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Clinic name cannot be empty.');
        }

        $clinic                   = new self();
        $clinic->id               = $id;
        $clinic->name             = $name;
        $clinic->slug             = $slug;
        $clinic->timeZone         = $timeZone;
        $clinic->locale           = $locale;
        $clinic->countryCode      = $countryCode;
        $clinic->jurisdictionCode = $jurisdictionCode;
        $clinic->currencyCode     = $currencyCode;
        $clinic->clinicGroupId    = $clinicGroupId;
        $clinic->status           = ClinicStatus::ACTIVE;
        $clinic->createdAt        = $createdAt;
        $clinic->updatedAt        = $createdAt;

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
        Locale $locale,
        CountryCode $countryCode,
        CurrencyCode $currencyCode,
        ClinicStatus $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        ?ClinicGroupId $clinicGroupId = null,
        ?string $jurisdictionCode = null,
    ): self {
        $clinic                   = new self();
        $clinic->id               = $id;
        $clinic->name             = $name;
        $clinic->slug             = $slug;
        $clinic->timeZone         = $timeZone;
        $clinic->locale           = $locale;
        $clinic->countryCode      = $countryCode;
        $clinic->jurisdictionCode = $jurisdictionCode;
        $clinic->currencyCode     = $currencyCode;
        $clinic->status           = $status;
        $clinic->clinicGroupId    = $clinicGroupId;
        $clinic->createdAt        = $createdAt;
        $clinic->updatedAt        = $updatedAt;

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

    public function changeLocale(Locale $newLocale, \DateTimeImmutable $updatedAt): void
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

    public function locale(): Locale
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

    public function countryCode(): CountryCode
    {
        return $this->countryCode;
    }

    public function jurisdictionCode(): ?string
    {
        return $this->jurisdictionCode;
    }

    public function currencyCode(): CurrencyCode
    {
        return $this->currencyCode;
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
