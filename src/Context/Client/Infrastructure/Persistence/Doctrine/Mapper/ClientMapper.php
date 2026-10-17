<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\ValueObject\AuthorizedPerson;
use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationPreferences;
use App\Context\Client\Domain\ValueObject\Consent;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Context\Client\Domain\ValueObject\ContactMethodType;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Embeddable\PostalAddressEmbeddable;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientEntity;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ContactMethodEntity;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Domain\ValueObject\PostalAddress;
use App\Shared\Domain\ValueObject\Siren;
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
                civility: Civility::tryFrom($entity->getCivility()) ?? Civility::UNSPECIFIED,
            ),
            status: $entity->getStatus(),
            contactMethods: $contactMethods,
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            postalAddress: $postalAddress,
            authorizedPerson: $this->authorizedPersonToDomain($entity),
            communicationPreferences: $this->communicationPreferencesToDomain($entity),
            taxProfile: $this->taxProfileToDomain($entity),
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
        $clientEntity->setCivility($client->identity()->civility->value);
        $clientEntity->setTaxStatus($client->taxProfile()->status());
        $clientEntity->setLegalName($client->taxProfile()->legalName());
        $clientEntity->setSiren($client->taxProfile()->siren()?->value());
        $clientEntity->setVatNumber($client->taxProfile()->vatNumber()?->value());
        $clientEntity->setStatus($client->status());
        $clientEntity->setCreatedAt($client->createdAt());
        $clientEntity->setUpdatedAt($client->updatedAt());

        $clientEntity->setPostalAddress($this->domainToEmbeddable($client->postalAddress()));
        $clientEntity->setAuthorizedPerson($this->authorizedPersonToArray($client->authorizedPerson()));
        $clientEntity->setCommunicationPreferences(
            $this->communicationPreferencesToArray($client->communicationPreferences()),
        );

        $contactMethodEntities = array_map(
            fn (ContactMethod $cm): ContactMethodEntity => $this->contactMethodToEntity($client->id(), $cm),
            $client->contactMethods(),
        );

        return [
            'client'         => $clientEntity,
            'contactMethods' => $contactMethodEntities,
        ];
    }

    private function taxProfileToDomain(ClientEntity $entity): ClientTaxProfile
    {
        if (ClientTaxStatus::INDIVIDUAL === $entity->getTaxStatus()) {
            return ClientTaxProfile::individual($entity->getLegalName());
        }

        $siren     = Siren::fromInput((string) $entity->getSiren());
        $vatNumber = $entity->getVatNumber();

        return ClientTaxProfile::frenchBusiness(
            (string) $entity->getLegalName(),
            $siren,
            null === $vatNumber ? null : FrenchVatNumber::fromInput($vatNumber, $siren),
        );
    }

    private function authorizedPersonToDomain(ClientEntity $entity): ?AuthorizedPerson
    {
        $data = $entity->getAuthorizedPerson();

        if (null === $data) {
            return null;
        }

        return new AuthorizedPerson(
            civility: Civility::from($data['civility']),
            firstName: $data['firstName'],
            lastName: $data['lastName'],
            relationship: AuthorizedPersonRelationship::from($data['relationship']),
            phoneNumber: null === $data['phoneNumber'] ? null : PhoneNumber::fromString($data['phoneNumber']),
            authorizedAt: new \DateTimeImmutable($data['authorizedAt']),
        );
    }

    /**
     * @return array{
     *     civility: string,
     *     firstName: string,
     *     lastName: string,
     *     relationship: string,
     *     phoneNumber: string|null,
     *     authorizedAt: string
     * }|null
     */
    private function authorizedPersonToArray(?AuthorizedPerson $person): ?array
    {
        if (null === $person) {
            return null;
        }

        return [
            'civility'     => $person->civility->value,
            'firstName'    => $person->firstName,
            'lastName'     => $person->lastName,
            'relationship' => $person->relationship->value,
            'phoneNumber'  => $person->phoneNumber?->toString(),
            'authorizedAt' => $person->authorizedAt->format(\DATE_ATOM),
        ];
    }

    private function communicationPreferencesToDomain(ClientEntity $entity): CommunicationPreferences
    {
        $data = $entity->getCommunicationPreferences();

        if (null === $data) {
            return CommunicationPreferences::none();
        }

        return new CommunicationPreferences(
            null === $data['preferredChannel'] ? null : ContactChannel::from($data['preferredChannel']),
            array_map(
                static fn (array $consent): Consent => new Consent(
                    ConsentKind::from($consent['kind']),
                    array_map(
                        static fn (string $channel): ContactChannel => ContactChannel::from($channel),
                        $consent['channels'],
                    ),
                    new \DateTimeImmutable($consent['grantedAt']),
                ),
                $data['consents'],
            ),
        );
    }

    /**
     * @return array{
     *     preferredChannel: string|null,
     *     consents: list<array{kind: string, channels: list<string>, grantedAt: string}>
     * }|null
     */
    private function communicationPreferencesToArray(CommunicationPreferences $preferences): ?array
    {
        if (null === $preferences->preferredChannel && [] === $preferences->consents) {
            return null;
        }

        return [
            'preferredChannel' => $preferences->preferredChannel?->value,
            'consents'         => array_map(
                static fn (Consent $consent): array => [
                    'kind'      => $consent->kind->value,
                    'channels'  => array_map(static fn (ContactChannel $c): string => $c->value, $consent->channels),
                    'grantedAt' => $consent->grantedAt->format(\DATE_ATOM),
                ],
                $preferences->consents,
            ),
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
