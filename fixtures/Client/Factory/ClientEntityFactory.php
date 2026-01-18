<?php

declare(strict_types=1);

namespace App\Fixtures\Client\Factory;

use App\Client\Domain\ValueObject\ClientStatus;
use App\Client\Infrastructure\Persistence\Doctrine\Embeddable\PostalAddressEmbeddable;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ClientEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ClientEntity>
 */
final class ClientEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ClientEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->with(['id' => Uuid::fromString($id)]);
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->with(['clinicId' => Uuid::fromString($clinicId)]);
    }

    public function withName(string $firstName, string $lastName): self
    {
        return $this->with([
            'firstName' => $firstName,
            'lastName'  => $lastName,
        ]);
    }

    public function active(): self
    {
        return $this->with(['status' => ClientStatus::ACTIVE]);
    }

    public function archived(): self
    {
        return $this->with(['status' => ClientStatus::ARCHIVED]);
    }

    public function withPostalAddress(
        string $streetLine1,
        string $city,
        string $countryCode,
        ?string $streetLine2 = null,
        ?string $postalCode = null,
        ?string $region = null,
    ): self {
        return $this->afterInstantiate(function (ClientEntity $client) use (
            $streetLine1,
            $city,
            $countryCode,
            $streetLine2,
            $postalCode,
            $region,
        ): void {
            $embeddable = new PostalAddressEmbeddable(
                streetLine1: $streetLine1,
                streetLine2: $streetLine2,
                postalCode: $postalCode,
                city: $city,
                region: $region,
                countryCode: $countryCode,
            );
            $client->setPostalAddress($embeddable);
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'id'        => Uuid::v7(),
            'clinicId'  => Uuid::v7(),
            'firstName' => self::faker()->firstName(),
            'lastName'  => self::faker()->lastName(),
            'status'    => self::faker()->randomElement([ClientStatus::ACTIVE, ClientStatus::ARCHIVED]),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween(
                '-2 years',
                '-1 month'
            )),
            'updatedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 month')),
        ];
    }
}
