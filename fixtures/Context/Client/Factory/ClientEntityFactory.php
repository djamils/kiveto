<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Client\Factory;

use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Embeddable\PostalAddressEmbeddable;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientEntity;
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

    /** 'madam' | 'mister' | 'unspecified' */
    public function withCivility(string $civility): self
    {
        return $this->with(['civility' => $civility]);
    }

    /** Client on file since that date (last changed on the same day unless told otherwise). */
    public function since(string $createdAt, ?string $updatedAt = null): self
    {
        return $this->with([
            'createdAt' => new \DateTimeImmutable($createdAt),
            'updatedAt' => new \DateTimeImmutable($updatedAt ?? $createdAt),
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

    public function withAuthorizedPerson(
        string $civility,
        string $firstName,
        string $lastName,
        string $relationship,
        ?string $phoneNumber,
        string $authorizedAt,
    ): self {
        return $this->afterInstantiate(static function (ClientEntity $client) use (
            $civility,
            $firstName,
            $lastName,
            $relationship,
            $phoneNumber,
            $authorizedAt,
        ): void {
            $client->setAuthorizedPerson([
                'civility'     => $civility,
                'firstName'    => $firstName,
                'lastName'     => $lastName,
                'relationship' => $relationship,
                'phoneNumber'  => $phoneNumber,
                'authorizedAt' => (new \DateTimeImmutable($authorizedAt))->format(\DATE_ATOM),
            ]);
        });
    }

    /**
     * @param array<string, list<string>> $channelsByConsentKind consent kind => channels, all granted on $grantedAt
     */
    public function withCommunicationPreferences(
        ?string $preferredChannel,
        array $channelsByConsentKind,
        string $grantedAt,
    ): self {
        return $this->afterInstantiate(static function (ClientEntity $client) use (
            $preferredChannel,
            $channelsByConsentKind,
            $grantedAt,
        ): void {
            $consents = [];

            foreach ($channelsByConsentKind as $kind => $channels) {
                $consents[] = [
                    'kind'      => $kind,
                    'channels'  => $channels,
                    'grantedAt' => (new \DateTimeImmutable($grantedAt))->format(\DATE_ATOM),
                ];
            }

            $client->setCommunicationPreferences([
                'preferredChannel' => $preferredChannel,
                'consents'         => $consents,
            ]);
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
