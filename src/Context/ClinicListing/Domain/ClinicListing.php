<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain;

/**
 * The directory's projection of one clinic (S7c): what Clinic says of it,
 * plus the coordinates its address was geocoded to. One per clinic, never
 * deleted: a clinic suspended or closed (or whose group is) is inactive and
 * leaves the directory, keeping its settings and coordinates for its return.
 *
 * The address hash is that of the address the coordinates belong to (or are
 * being looked up for): a refresh that leaves the normalised address as it is
 * asks for no geocoding; one that changes it keeps the previous coordinates
 * until the new ones are attached.
 */
final class ClinicListing
{
    private ClinicListingId $id;
    private string $clinicId;
    private ListingProfile $profile;
    private ?ListingCoordinates $coordinates;
    private ?string $addressHash;
    private \DateTimeImmutable $refreshedAt;

    private function __construct()
    {
    }

    /**
     * The first projection of a clinic: its address, if it has one, is still
     * to be geocoded.
     */
    public static function project(ClinicListingId $id, string $clinicId, ListingProfile $profile, \DateTimeImmutable $now): self
    {
        $listing              = new self();
        $listing->id          = $id;
        $listing->clinicId    = $clinicId;
        $listing->profile     = $profile;
        $listing->coordinates = null;
        $listing->addressHash = $profile->address()?->hash();
        $listing->refreshedAt = $now;

        return $listing;
    }

    public static function reconstitute(
        ClinicListingId $id,
        string $clinicId,
        ListingProfile $profile,
        ?ListingCoordinates $coordinates,
        ?string $addressHash,
        \DateTimeImmutable $refreshedAt,
    ): self {
        $listing              = new self();
        $listing->id          = $id;
        $listing->clinicId    = $clinicId;
        $listing->profile     = $profile;
        $listing->coordinates = $coordinates;
        $listing->addressHash = $addressHash;
        $listing->refreshedAt = $refreshedAt;

        return $listing;
    }

    /**
     * Takes what Clinic now says of the clinic. Returns true when the address
     * must be geocoded (again): it changed (normalised), or it has no
     * coordinates yet (an earlier geocoding failed or could not place it).
     * An address removed drops the coordinates at once.
     */
    public function refresh(ListingProfile $profile, \DateTimeImmutable $now): bool
    {
        $hash = $profile->address()?->hash();

        $this->profile     = $profile;
        $this->refreshedAt = $now;

        if ($hash === $this->addressHash) {
            return null !== $hash && null === $this->coordinates;
        }

        $this->addressHash = $hash;

        if (null === $hash) {
            $this->coordinates = null;

            return false;
        }

        return true;
    }

    /**
     * The geocoder's answer for the address of that hash: null when it could
     * not place it. An answer for an address that has changed since is
     * stale and ignored (returns false).
     */
    public function attachCoordinates(string $addressHash, ?ListingCoordinates $coordinates): bool
    {
        if ($addressHash !== $this->addressHash) {
            return false;
        }

        $this->coordinates = $coordinates;

        return true;
    }

    public function id(): ClinicListingId
    {
        return $this->id;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function profile(): ListingProfile
    {
        return $this->profile;
    }

    public function coordinates(): ?ListingCoordinates
    {
        return $this->coordinates;
    }

    public function addressHash(): ?string
    {
        return $this->addressHash;
    }

    public function refreshedAt(): \DateTimeImmutable
    {
        return $this->refreshedAt;
    }

    public function isActive(): bool
    {
        return $this->profile->isActive();
    }
}
