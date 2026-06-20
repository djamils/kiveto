<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierContact;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierStatus;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierEntity;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Symfony\Component\Uid\Uuid;

final readonly class SupplierMapper
{
    public function toEntity(Supplier $supplier): SupplierEntity
    {
        $entity = new SupplierEntity();

        $entity->setId(Uuid::fromString($supplier->id()->toString()));
        $entity->setName($supplier->name()->toString());
        $entity->setCode($supplier->code()->toString());
        $entity->setType($supplier->type()->value);
        $entity->setCountryCode($supplier->countryCode()->toString());
        $entity->setDefaultCurrency($supplier->defaultCurrency()->toString());
        $entity->setIntegrationMode($supplier->integrationMode()->value);
        $entity->setAdapterIdentifier($supplier->adapterIdentifier());
        $entity->setStatus($supplier->status()->value);
        $entity->setCreatedAt($supplier->createdAt());
        $entity->setUpdatedAt($supplier->updatedAt());

        $contact = $supplier->contact();
        if (null !== $contact) {
            $entity->setContactEmail($contact->email);
            $entity->setContactPhone($contact->phone);
            $entity->setContactPerson($contact->contactPerson);
            $entity->setContactAddressStreet($contact->address?->street);
            $entity->setContactAddressLine2($contact->address?->addressLine2);
            $entity->setContactPostalCode($contact->address?->postalCode);
            $entity->setContactCity($contact->address?->city);
            $entity->setContactAddressCountryCode($contact->address?->countryCode?->toString());
        } else {
            $entity->setContactEmail(null);
            $entity->setContactPhone(null);
            $entity->setContactPerson(null);
            $entity->setContactAddressStreet(null);
            $entity->setContactAddressLine2(null);
            $entity->setContactPostalCode(null);
            $entity->setContactCity(null);
            $entity->setContactAddressCountryCode(null);
        }

        return $entity;
    }

    public function toDomain(SupplierEntity $entity): Supplier
    {
        $contact = null;

        $hasContactData = null !== $entity->getContactEmail()
            || null !== $entity->getContactPhone()
            || null !== $entity->getContactPerson()
            || null !== $entity->getContactAddressStreet();

        if ($hasContactData) {
            $address = null;

            $hasAddressField = null !== $entity->getContactAddressStreet()
                || null !== $entity->getContactAddressLine2()
                || null !== $entity->getContactPostalCode()
                || null !== $entity->getContactCity()
                || null !== $entity->getContactAddressCountryCode();

            if ($hasAddressField) {
                $addressCountryCode = null !== $entity->getContactAddressCountryCode()
                    ? CountryCode::fromString($entity->getContactAddressCountryCode())
                    : null;

                $address = Address::create(
                    $entity->getContactAddressStreet(),
                    $entity->getContactAddressLine2(),
                    $entity->getContactPostalCode(),
                    $entity->getContactCity(),
                    $addressCountryCode,
                );
            }

            $contact = SupplierContact::create(
                $entity->getContactEmail(),
                $entity->getContactPhone(),
                $entity->getContactPerson(),
                $address,
            );
        }

        return Supplier::reconstitute(
            id: SupplierId::fromString($entity->getId()->toString()),
            name: SupplierName::fromString($entity->getName()),
            code: SupplierCode::fromString($entity->getCode()),
            type: SupplierType::from($entity->getType()),
            countryCode: CountryCode::fromString($entity->getCountryCode()),
            defaultCurrency: CurrencyCode::fromString($entity->getDefaultCurrency()),
            integrationMode: SupplierIntegrationMode::from($entity->getIntegrationMode()),
            adapterIdentifier: $entity->getAdapterIdentifier(),
            contact: $contact,
            status: SupplierStatus::from($entity->getStatus()),
            version: $entity->getVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
