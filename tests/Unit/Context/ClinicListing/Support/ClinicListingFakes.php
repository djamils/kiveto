<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Support;

use App\Context\ClinicListing\Application\Port\AddressGeocoderInterface;
use App\Context\ClinicListing\Application\Port\ClinicProfileInterface;
use App\Context\ClinicListing\Application\Port\OnlineBookingStatusInterface;
use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingCoordinates;
use App\Context\ClinicListing\Domain\ListingProfile;
use App\Context\ClinicListing\Domain\ListingSettings;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use App\Context\ClinicListing\Domain\Repository\ListingSettingsRepositoryInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;

/**
 * In-memory doubles of the ClinicListing ports and repositories, all in one
 * object so that a test reads what every side saw.
 */
final class ClinicListingFakes implements ClinicListingRepositoryInterface, ListingSettingsRepositoryInterface, ClinicProfileInterface, AddressGeocoderInterface, OnlineBookingStatusInterface, ExternalApiCommandBusInterface, UuidGeneratorInterface
{
    /** @var array<string, ClinicListing> by clinic id */
    public array $listings = [];

    /** @var array<string, ListingSettings> by clinic id */
    public array $settings = [];

    /** @var array<string, ListingProfile> by clinic id */
    public array $profiles = [];

    /** @var list<string> clinics Clinic lists but cannot read */
    public array $ghostIds = [];

    /** @var list<ExternalApiCommandInterface> */
    public array $sent = [];

    /** @var list<string> the addresses geocoded */
    public array $geocoded = [];

    /** @var list<list<string>> the clinic ids asked to Scheduling, per call */
    public array $askedOpen = [];

    /** @var list<string> */
    public array $openToNewClients = [];

    public ?ListingCoordinates $coordinates = null;

    public ?\Throwable $geocoderFailure = null;

    public int $saves = 0;

    private int $nextId = 0;

    public static function profile(
        string $name = 'Clinique du Parc',
        ?string $line = '18 rue des Abbesses',
        ?string $city = 'Paris',
        bool $active = true,
        ?string $postalCode = '75018',
    ): ListingProfile {
        return new ListingProfile(
            name: $name,
            address: ListingAddress::fromParts($line, $postalCode, $city),
            phone: '+33142551890',
            timezone: 'Europe/Paris',
            openingHours: [['day' => 1, 'opensAt' => '09:00', 'closesAt' => '19:00']],
            active: $active,
        );
    }

    public function save(ClinicListing|ListingSettings $item): void
    {
        if ($item instanceof ClinicListing) {
            $this->listings[$item->clinicId()] = $item;
            ++$this->saves;

            return;
        }

        $this->settings[$item->clinicId()] = $item;
    }

    public function saveCoordinates(ClinicListing $listing): void
    {
        if (($this->listings[$listing->clinicId()] ?? null)?->addressHash() === $listing->addressHash()) {
            $this->listings[$listing->clinicId()] = $listing;
            ++$this->saves;
        }
    }

    public function findByClinicId(string $clinicId): ?ClinicListing
    {
        return $this->listings[$clinicId] ?? null;
    }

    public function forClinic(string $clinicId): ListingSettings
    {
        return $this->settings[$clinicId] ?? ListingSettings::defaults($clinicId);
    }

    public function profileOf(string $clinicId): ?ListingProfile
    {
        return $this->profiles[$clinicId] ?? null;
    }

    public function clinicIds(): array
    {
        return [...array_keys($this->profiles), ...$this->ghostIds];
    }

    public function geocode(ListingAddress $address): ?ListingCoordinates
    {
        $this->geocoded[] = $address->toQuery();

        if (null !== $this->geocoderFailure) {
            throw $this->geocoderFailure;
        }

        return $this->coordinates;
    }

    public function openToNewClients(array $clinicIds): array
    {
        $this->askedOpen[] = $clinicIds;

        return array_values(array_intersect($clinicIds, $this->openToNewClients));
    }

    public function send(ExternalApiCommandInterface $command, object ...$stamps): void
    {
        $this->sent[] = $command;
    }

    public function generate(): string
    {
        return \sprintf('01960000-0000-7000-8000-%012d', ++$this->nextId);
    }
}
