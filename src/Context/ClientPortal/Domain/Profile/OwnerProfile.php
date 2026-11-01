<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Profile;

use App\Shared\Domain\ValueObject\PostalAddress;

/**
 * Who the owner account is, for the Portal: one per PORTAL user, copied from
 * the client file of the first accepted invitation and never overwritten by
 * later ones. Each acceptance records the version of the terms of use. The
 * owner edits the name and the address (`PATCH /me`); the clinics' client
 * files are never touched.
 */
final class OwnerProfile
{
    public const int NAME_MAX_LENGTH = 60;

    /** What an erased profile reads: « Compte supprimé ». */
    public const string ANONYMISED_FIRST_NAME = 'Compte';
    public const string ANONYMISED_LAST_NAME  = 'supprimé';

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

    /**
     * Each name is trimmed and keeps 1 to 60 characters.
     *
     * @throws \InvalidArgumentException for an empty or too long name
     */
    public function rename(string $firstName, string $lastName): void
    {
        $this->firstName = self::name($firstName);
        $this->lastName  = self::name($lastName);
    }

    /** A postal address entered by the owner, never geocoded. */
    public function changeAddress(PostalAddress $address): void
    {
        $this->address = $address;
    }

    /**
     * The account was deleted: the name reads « Compte supprimé » and the
     * address is erased. The terms acceptance (version, date) stays.
     */
    public function anonymise(): void
    {
        $this->firstName = self::ANONYMISED_FIRST_NAME;
        $this->lastName  = self::ANONYMISED_LAST_NAME;
        $this->address   = null;
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

    private static function name(string $name): string
    {
        $name   = trim($name);
        $length = mb_strlen($name);

        if (0 === $length || $length > self::NAME_MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('A profile name has 1 to %d characters.', self::NAME_MAX_LENGTH));
        }

        return $name;
    }
}
