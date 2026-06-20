<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountStatus;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierAccountEntity;
use Symfony\Component\Uid\Uuid;

final readonly class SupplierAccountMapper
{
    public function toEntity(SupplierAccount $account): SupplierAccountEntity
    {
        $entity = new SupplierAccountEntity();

        $entity->setId(Uuid::fromString($account->id()->toString()));
        $entity->setClinicId(Uuid::fromString($account->clinicId()->toString()));
        $entity->setSupplierId(Uuid::fromString($account->supplierId()->toString()));
        $entity->setCustomerCode($account->customerCode()->toString());
        $entity->setStatus($account->status()->value);
        $entity->setNotes($account->notes());
        $entity->setCreatedAt($account->createdAt());
        $entity->setUpdatedAt($account->updatedAt());

        $billingAddress = $account->billingAddress();
        $entity->setBillingAddressJson(
            null !== $billingAddress
                ? json_encode($billingAddress->toArray(), \JSON_THROW_ON_ERROR)
                : null
        );

        $deliveryAddress = $account->defaultDeliveryAddress();
        $entity->setDeliveryAddressJson(
            null !== $deliveryAddress
                ? json_encode($deliveryAddress->toArray(), \JSON_THROW_ON_ERROR)
                : null
        );

        return $entity;
    }

    public function toDomain(SupplierAccountEntity $entity): SupplierAccount
    {
        $billingAddress = null;
        $billingJson    = $entity->getBillingAddressJson();
        if (null !== $billingJson) {
            /** @var array<string, string|null> $billingData */
            $billingData    = json_decode($billingJson, true, 512, \JSON_THROW_ON_ERROR);
            $billingAddress = Address::fromArray($billingData);
        }

        $deliveryAddress = null;
        $deliveryJson    = $entity->getDeliveryAddressJson();
        if (null !== $deliveryJson) {
            /** @var array<string, string|null> $deliveryData */
            $deliveryData    = json_decode($deliveryJson, true, 512, \JSON_THROW_ON_ERROR);
            $deliveryAddress = Address::fromArray($deliveryData);
        }

        return SupplierAccount::reconstitute(
            id: SupplierAccountId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toString()),
            supplierId: SupplierId::fromString($entity->getSupplierId()->toString()),
            customerCode: CustomerCode::fromString($entity->getCustomerCode()),
            status: SupplierAccountStatus::from($entity->getStatus()),
            billingAddress: $billingAddress,
            defaultDeliveryAddress: $deliveryAddress,
            notes: $entity->getNotes(),
            version: $entity->getVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
