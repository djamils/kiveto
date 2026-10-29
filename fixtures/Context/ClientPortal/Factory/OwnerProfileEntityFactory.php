<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClientPortal\Factory;

use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\OwnerProfileEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * The Portal profile of an owner account: the name (and address, when the
 * client file had one) of the first accepted invitation, and the terms the
 * owner accepted. Dates are UTC.
 *
 * @extends PersistentProxyObjectFactory<OwnerProfileEntity>
 */
final class OwnerProfileEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return OwnerProfileEntity::class;
    }

    public function forUser(string $userId, string $firstName, string $lastName): self
    {
        return $this->with(['userId' => Uuid::fromString($userId), 'firstName' => $firstName, 'lastName' => $lastName]);
    }

    public function withAddress(string $line1, string $postalCode, string $city, string $country = 'FR'): self
    {
        return $this->with([
            'addressLine1'      => $line1,
            'addressPostalCode' => $postalCode,
            'addressCity'       => $city,
            'addressCountry'    => $country,
        ]);
    }

    /** Created when the terms were first accepted. */
    public function acceptedTerms(string $version, \DateTimeImmutable $acceptedAt): self
    {
        $utc = $acceptedAt->setTimezone(new \DateTimeZone('UTC'));

        return $this->with(['termsVersion' => $version, 'termsAcceptedAt' => $utc, 'createdAt' => $utc]);
    }

    protected function defaults(): array|callable
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return [
            'userId'            => Uuid::v7(),
            'firstName'         => self::faker()->firstName(),
            'lastName'          => self::faker()->lastName(),
            'addressLine1'      => null,
            'addressLine2'      => null,
            'addressPostalCode' => null,
            'addressCity'       => null,
            'addressCountry'    => null,
            'termsVersion'      => '2026-10-29',
            'termsAcceptedAt'   => $now,
            'createdAt'         => $now,
        ];
    }
}
