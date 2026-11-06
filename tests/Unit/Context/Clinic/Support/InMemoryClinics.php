<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Support;

use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Clinic\Domain\ValueObject\OpeningHours;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\Siren;

final class InMemoryClinics implements ClinicRepositoryInterface
{
    /** @var array<string, Clinic> */
    public array $clinics = [];

    /** @var list<string> ids saved, in order */
    public array $saved = [];

    /**
     * A clinic of the console, created on `createdAt` (UTC).
     */
    public static function clinic(
        string $id,
        ClinicStatus $status = ClinicStatus::ACTIVE,
        ?OpeningHours $openingHours = null,
        string $createdAt = '2026-09-01 08:00:00',
        string $slug = 'clinique-test',
    ): Clinic {
        $at = new \DateTimeImmutable($createdAt, new \DateTimeZone('UTC'));

        return Clinic::reconstitute(
            ClinicId::fromString($id),
            'Clinique ' . $id,
            ClinicSlug::fromString($slug),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            CountryCode::fromString('FR'),
            CurrencyCode::fromString('EUR'),
            $status,
            $at,
            $at,
            openingHours: $openingHours,
        );
    }

    public function add(Clinic $clinic): self
    {
        $this->clinics[$clinic->id()->toString()] = $clinic;

        return $this;
    }

    public function save(Clinic $clinic): void
    {
        $this->clinics[$clinic->id()->toString()] = $clinic;
        $this->saved[]                            = $clinic->id()->toString();
    }

    public function findById(ClinicId $id): ?Clinic
    {
        return $this->clinics[$id->toString()] ?? null;
    }

    public function findBySlug(ClinicSlug $slug): ?Clinic
    {
        foreach ($this->clinics as $clinic) {
            if ($clinic->slug()->toString() === $slug->toString()) {
                return $clinic;
            }
        }

        return null;
    }

    public function existsBySlug(ClinicSlug $slug): bool
    {
        return null !== $this->findBySlug($slug);
    }

    public function findOthersWithSiren(Siren $siren, ClinicId $excluding): array
    {
        return [];
    }
}
