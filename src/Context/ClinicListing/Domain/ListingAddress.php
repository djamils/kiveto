<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain;

/**
 * The clinic's address as the directory shows and geocodes it: its street
 * line (both letterhead lines joined), postal code and city.
 */
final readonly class ListingAddress
{
    private string $line;
    private ?string $postalCode;
    private string $city;

    private function __construct(string $line, ?string $postalCode, string $city)
    {
        $this->line       = $line;
        $this->postalCode = $postalCode;
        $this->city       = $city;
    }

    /**
     * Null when the street line or the city is missing: such an address can
     * neither be shown as one nor geocoded.
     */
    public static function fromParts(?string $line, ?string $postalCode, ?string $city): ?self
    {
        $line       = self::clean($line);
        $postalCode = self::clean($postalCode);
        $city       = self::clean($city);

        if (null === $line || null === $city) {
            return null;
        }

        return new self($line, $postalCode, $city);
    }

    public function line(): string
    {
        return $this->line;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    public function city(): string
    {
        return $this->city;
    }

    /**
     * What a geocoder is asked: « 18 rue des Abbesses 75018 Paris ».
     */
    public function toQuery(): string
    {
        return implode(' ', array_filter([$this->line, $this->postalCode, $this->city], static fn (?string $part): bool => null !== $part));
    }

    /**
     * SHA-256 of the normalised address (lower case, single spaces): two
     * spellings differing only by case or spacing do not geocode again.
     */
    public function hash(): string
    {
        $normalised = mb_strtolower(implode('|', [$this->line, $this->postalCode ?? '', $this->city]));

        return hash('sha256', (string) preg_replace('/\s+/u', ' ', $normalised));
    }

    public function equals(self $other): bool
    {
        return $this->line === $other->line && $this->postalCode === $other->postalCode && $this->city === $other->city;
    }

    private static function clean(?string $value): ?string
    {
        $value = mb_trim((string) preg_replace('/\s+/u', ' ', $value ?? ''));

        return '' === $value ? null : $value;
    }
}
