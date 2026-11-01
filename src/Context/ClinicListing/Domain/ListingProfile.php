<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain;

/**
 * What the Clinic context says of a clinic, as the directory shows it: its
 * name, address and phone, time zone, weekly opening hours, and whether it
 * is active (the clinic, and its group if it has one).
 */
final readonly class ListingProfile
{
    private string $name;
    private ?ListingAddress $address;
    private ?string $phone;
    private string $timezone;
    /** @var list<array{day: int, opensAt: string, closesAt: string}> */
    private array $openingHours;
    private bool $active;

    /**
     * @param list<array{day: int, opensAt: string, closesAt: string}> $openingHours by day (1 = Monday), then opening time
     */
    public function __construct(
        string $name,
        ?ListingAddress $address,
        ?string $phone,
        string $timezone,
        array $openingHours,
        bool $active,
    ) {
        $this->name         = $name;
        $this->address      = $address;
        $this->phone        = $phone;
        $this->timezone     = $timezone;
        $this->openingHours = $openingHours;
        $this->active       = $active;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function address(): ?ListingAddress
    {
        return $this->address;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    /**
     * @return list<array{day: int, opensAt: string, closesAt: string}>
     */
    public function openingHours(): array
    {
        return $this->openingHours;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
