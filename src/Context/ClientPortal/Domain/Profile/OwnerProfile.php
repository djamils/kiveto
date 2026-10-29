<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Profile;

use App\Shared\Domain\ValueObject\PostalAddress;

/**
 * Who the owner account is, for the Portal: one per PORTAL user, copied from
 * the client file of the first accepted invitation and never overwritten by
 * later ones. Each acceptance records the version of the terms of use.
 */
final class OwnerProfile
{
    private string $userId;
    private string $firstName;
    private string $lastName;
    private ?PostalAddress $address;
    private string $termsVersion;
    private \DateTimeImmutable $termsAcceptedAt;
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function createFromClient(
        string $userId,
        string $firstName,
        string $lastName,
        ?PostalAddress $address,
        string $termsVersion,
        \DateTimeImmutable $now,
    ): self {
        $profile                  = new self();
        $profile->userId          = $userId;
        $profile->firstName       = $firstName;
        $profile->lastName        = $lastName;
        $profile->address         = $address;
        $profile->termsVersion    = $termsVersion;
        $profile->termsAcceptedAt = $now;
        $profile->createdAt       = $now;

        return $profile;
    }

    public static function reconstitute(
        string $userId,
        string $firstName,
        string $lastName,
        ?PostalAddress $address,
        string $termsVersion,
        \DateTimeImmutable $termsAcceptedAt,
        \DateTimeImmutable $createdAt,
    ): self {
        $profile                  = new self();
        $profile->userId          = $userId;
        $profile->firstName       = $firstName;
        $profile->lastName        = $lastName;
        $profile->address         = $address;
        $profile->termsVersion    = $termsVersion;
        $profile->termsAcceptedAt = $termsAcceptedAt;
        $profile->createdAt       = $createdAt;

        return $profile;
    }

    public function acceptTerms(string $version, \DateTimeImmutable $now): void
    {
        $this->termsVersion    = $version;
        $this->termsAcceptedAt = $now;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function address(): ?PostalAddress
    {
        return $this->address;
    }

    public function termsVersion(): string
    {
        return $this->termsVersion;
    }

    public function termsAcceptedAt(): \DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
