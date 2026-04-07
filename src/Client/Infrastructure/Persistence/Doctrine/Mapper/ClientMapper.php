<?php

declare(strict_types=1);

namespace App\Client\Infrastructure\Persistence\Doctrine\Mapper;

use App\Client\Domain\Client;
use App\Client\Domain\ValueObject\ClientId;
use App\Client\Domain\ValueObject\ClientIdentity;
use App\Client\Domain\ValueObject\ContactMethod;
use App\Client\Domain\ValueObject\ContactMethodType;
use App\Client\Infrastructure\Persistence\Doctrine\Embeddable\PostalAddressEmbeddable;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ClientEntity;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ContactMethodEntity;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Domain\ValueObject\PostalAddress;
use Symfony\Component\Uid\Uuid;

final readonly class ClientMapper
{
    /**
     * @param list<ContactMethodEntity> $contactMethodEntities
     */
    public function toDomain(ClientEntity $entity, array $contactMethodEntities): Client
    {
        $contactMethods = array_map(
            fn (ContactMethodEntity $cme): ContactMethod => $this->contactMethodToDomain($cme),
            $contactMethodEntities,
        );

        $postalAddress = $this->embeddableToDomain($entity->getPostalAddress());

        return Client::reconstitute(
            id: ClientId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toString()),
            identity: new ClientIdentity(
                firstName: $entity->getFirstName(),
                lastName: $entity->getLastName(),
            ),
            status: $entity->getStatus(),
            contactMethods: $contactMethods,
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            postalAddress: $postalAddress,
        );
    }

    /**
     * @return array{client: ClientEntity, contactMethods: list<ContactMethodEntity>}
     */
    public function toEntity(Client $client): array
    {
        $clientEntity = new ClientEntity();
        $clientEntity->setId(Uuid::fromString($client->id()->toString()));
        $clientEntity->setClinicId(Uuid::fromString($client->clinicId()->toString()));
        $clientEntity->setFirstName($client->identity()->firstName);
        $clientEntity->setLastName($client->identity()->lastName);
        $clientEntity->setStatus($client->status());
        $clientEntity->setCreatedAt($client->createdAt());
        $clientEntity->setUpdatedAt($client->updatedAt());

        $clientEntity->setPostalAddress($this->domainToEmbeddable($client->postalAddress()));

        $contactMethodEntities = array_map(
            fn (ContactMethod $cm): ContactMethodEntity => $this->contactMethodToEntity($client->id(), $cm),
            $client->contactMethods(),
        );

        return [
            'client'         => $clientEntity,
            'contactMethods' => $contactMethodEntities,
        ];
    }

    private function contactMethodToDomain(ContactMethodEntity $entity): ContactMethod
    {
        if (ContactMethodType::PHONE === $entity->getType()) {
            return ContactMethod::phone(
                PhoneNumber::fromString($entity->getValue()),
                $entity->getLabel(),
                $entity->isPrimary(),
            );
        }

        return ContactMethod::email(
            EmailAddress::fromString($entity->getValue()),
            $entity->getLabel(),
            $entity->isPrimary(),
        );
    }

    private function contactMethodToEntity(ClientId $clientId, ContactMethod $contactMethod): ContactMethodEntity
    {
        $entity = new ContactMethodEntity();
        $entity->setId(Uuid::v7());
        $entity->setClientId(Uuid::fromString($clientId->toString()));
        $entity->setType($contactMethod->type);
        $entity->setLabel($contactMethod->label);
        $entity->setValue($contactMethod->value);
        $entity->setIsPrimary($contactMethod->isPrimary);

        return $entity;
    }

    private function embeddableToDomain(PostalAddressEmbeddable $embeddable): ?PostalAddress
    {
        $isEmpty    = $embeddable->isEmpty();
        $hasStreet  = null !== $embeddable->streetLine1;
        $hasCity    = null !== $embeddable->city;
        $hasCountry = null !== $embeddable->countryCode;

        if ($isEmpty || !$hasStreet || !$hasCity || !$hasCountry) {
            return null;
        }

        \assert(null !== $embeddable->streetLine1);
        \assert(null !== $embeddable->city);
        \assert(null !== $embeddable->countryCode);

        return PostalAddress::create(
            streetLine1: $embeddable->streetLine1,
            city: $embeddable->city,
            countryCode: $embeddable->countryCode,
            streetLine2: $embeddable->streetLine2,
            postalCode: $embeddable->postalCode,
            region: $embeddable->region,
        );
    }

    private function domainToEmbeddable(?PostalAddress $address): PostalAddressEmbeddable
    {
        if (null === $address) {
            return new PostalAddressEmbeddable();
        }

        return new PostalAddressEmbeddable(
            streetLine1: $address->streetLine1,
            streetLine2: $address->streetLine2,
            postalCode: $address->postalCode,
            city: $address->city,
            region: $address->region,
            countryCode: $address->countryCode,
        );
    }
}
