<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinicGroup\ClinicGroupDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinicGroup\GetClinicGroup;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Clinic\ListClinics\ClinicCollection;
use App\Context\Clinic\Application\Query\Clinic\ListClinics\ListClinics;
use App\Context\ClinicListing\Application\Port\ClinicProfileInterface;
use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingProfile;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Clinic's queries: GetClinic (status, group), GetClinicGroup (the group's
 * status), GetClinicSettings (the letterhead's address and phone, the
 * opening week) and ListClinics. A clinic is active when it is and its group,
 * if it has one, is too.
 */
final readonly class QueryBusClinicProfile implements ClinicProfileInterface
{
    private const string ACTIVE = 'active';

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function profileOf(string $clinicId): ?ListingProfile
    {
        $clinic   = $this->queryBus->ask(new GetClinic($clinicId));
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));

        if (!$clinic instanceof ClinicDto || !$settings instanceof ClinicSettingsView) {
            return null;
        }

        $letterhead = $settings->letterhead;
        $line       = implode(', ', array_filter([$letterhead['streetLine1'] ?? null, $letterhead['streetLine2'] ?? null], static fn (?string $part): bool => null !== $part && '' !== mb_trim($part)));

        return new ListingProfile(
            name: $clinic->name,
            address: ListingAddress::fromParts($line, $letterhead['postalCode'] ?? null, $letterhead['city'] ?? null),
            phone: $letterhead['phone'] ?? null,
            timezone: $clinic->timeZone,
            openingHours: self::week($settings->week),
            active: self::ACTIVE === $clinic->status->value && $this->groupIsActive($clinic->clinicGroupId),
        );
    }

    public function clinicIds(): array
    {
        $clinics = $this->queryBus->ask(new ListClinics());

        return $clinics instanceof ClinicCollection
            ? array_map(static fn (ClinicDto $clinic): string => $clinic->id, $clinics->clinics)
            : [];
    }

    private function groupIsActive(?string $clinicGroupId): bool
    {
        if (null === $clinicGroupId) {
            return true;
        }

        $group = $this->queryBus->ask(new GetClinicGroup($clinicGroupId));

        return !$group instanceof ClinicGroupDto || self::ACTIVE === $group->status->value;
    }

    /**
     * @param array<int, list<array{opensAt: string, closesAt: string}>> $week
     *
     * @return list<array{day: int, opensAt: string, closesAt: string}>
     */
    private static function week(array $week): array
    {
        ksort($week);
        $slots = [];

        foreach ($week as $day => $daySlots) {
            foreach ($daySlots as $slot) {
                $slots[] = ['day' => $day, 'opensAt' => $slot['opensAt'], 'closesAt' => $slot['closesAt']];
            }
        }

        return $slots;
    }
}
