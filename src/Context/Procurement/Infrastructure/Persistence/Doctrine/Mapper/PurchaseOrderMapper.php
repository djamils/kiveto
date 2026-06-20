<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Procurement\Domain\PurchaseOrder\Entity\PurchaseOrderLine;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineStatus;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderStatus;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\PurchaseOrderEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\PurchaseOrderLineEntity;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Uid\Uuid;

final readonly class PurchaseOrderMapper
{
    public function toEntity(PurchaseOrder $order): PurchaseOrderEntity
    {
        $entity = new PurchaseOrderEntity();

        $entity->setId(Uuid::fromString($order->id()->toString()));
        $entity->setClinicId(Uuid::fromString($order->clinicId()->toString()));
        $entity->setSupplierId(Uuid::fromString($order->supplierId()->toString()));
        $entity->setSupplierAccountId(Uuid::fromString($order->supplierAccountId()->toString()));
        $entity->setOrderNumber($order->orderNumber()->toString());
        $entity->setCurrency($order->currency()->toString());
        $entity->setStatus($order->status()->value);
        $entity->setExternalReferenceValue($order->externalReference()?->value);
        $entity->setExternalReferenceProvidedBy($order->externalReference()?->providedBy);
        $entity->setDeliveryAddressJson(
            json_encode($order->deliveryAddress()->toArray(), \JSON_THROW_ON_ERROR)
        );
        $entity->setPdfFileId($order->pdfFileId());
        $entity->setSubmittedAt($order->submittedAt());
        $entity->setConfirmedAt($order->confirmedAt());
        $entity->setCreatedAt($order->createdAt());
        $entity->setUpdatedAt($order->updatedAt());

        $entity->clearLines();

        foreach ($order->lines() as $line) {
            $lineEntity = new PurchaseOrderLineEntity();
            $lineEntity->setId(Uuid::fromString($line->id()->toString()));
            $lineEntity->setArticleId(Uuid::fromString($line->articleId()->toString()));
            $lineEntity->setCatalogEntryId(Uuid::fromString($line->catalogEntryId()->toString()));
            $lineEntity->setOrderedAmount($line->orderedAmount());
            $lineEntity->setOrderedUnit($line->orderedUnit()->toString());
            $lineEntity->setUnitPriceMinor($line->unitPrice()->minorUnits());
            $lineEntity->setUnitPriceCurrency($line->unitPrice()->currency()->toString());
            $lineEntity->setReceivedAmount($line->receivedAmount());
            $lineEntity->setStatus($line->status()->value);
            $lineEntity->setNote($line->note());

            $entity->addLine($lineEntity);
        }

        return $entity;
    }

    public function toDomain(PurchaseOrderEntity $entity): PurchaseOrder
    {
        $externalReference = null;
        if (null !== $entity->getExternalReferenceValue() && null !== $entity->getExternalReferenceProvidedBy()) {
            $externalReference = ExternalReference::create(
                $entity->getExternalReferenceValue(),
                $entity->getExternalReferenceProvidedBy(),
            );
        }

        $deliveryAddressJson = $entity->getDeliveryAddressJson();
        if (null === $deliveryAddressJson) {
            throw new \RuntimeException('PurchaseOrder delivery_address_json must not be null.');
        }

        /** @var array<string, string|null> $deliveryData */
        $deliveryData    = json_decode($deliveryAddressJson, true, 512, \JSON_THROW_ON_ERROR);
        $deliveryAddress = Address::fromArray($deliveryData);

        $lines = [];
        foreach ($entity->getLines() as $lineEntity) {
            $orderedAmount  = $lineEntity->getOrderedAmount();
            $receivedAmount = $lineEntity->getReceivedAmount();

            if (!is_numeric($orderedAmount)) {
                throw new \RuntimeException(\sprintf('Invalid orderedAmount "%s": expected a numeric string.', $orderedAmount));
            }

            if (!is_numeric($receivedAmount)) {
                throw new \RuntimeException(\sprintf('Invalid receivedAmount "%s": expected a numeric string.', $receivedAmount));
            }

            /* @var numeric-string $orderedAmount */
            /* @var numeric-string $receivedAmount */
            $lines[] = PurchaseOrderLine::reconstitute(
                id: PurchaseOrderLineId::fromString($lineEntity->getId()->toString()),
                articleId: ArticleId::fromString($lineEntity->getArticleId()->toString()),
                catalogEntryId: SupplierCatalogEntryId::fromString($lineEntity->getCatalogEntryId()->toString()),
                orderedAmount: $orderedAmount,
                orderedUnit: UnitOfMeasure::fromString($lineEntity->getOrderedUnit()),
                unitPrice: Money::fromMinorUnits(
                    $lineEntity->getUnitPriceMinor(),
                    CurrencyCode::fromString($lineEntity->getUnitPriceCurrency()),
                ),
                receivedAmount: $receivedAmount,
                status: PurchaseOrderLineStatus::from($lineEntity->getStatus()),
                note: $lineEntity->getNote(),
            );
        }

        return PurchaseOrder::reconstitute(
            id: PurchaseOrderId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toString()),
            supplierId: SupplierId::fromString($entity->getSupplierId()->toString()),
            supplierAccountId: SupplierAccountId::fromString($entity->getSupplierAccountId()->toString()),
            orderNumber: PurchaseOrderNumber::fromString($entity->getOrderNumber()),
            currency: CurrencyCode::fromString($entity->getCurrency()),
            status: PurchaseOrderStatus::from($entity->getStatus()),
            externalReference: $externalReference,
            deliveryAddress: $deliveryAddress,
            pdfFileId: $entity->getPdfFileId(),
            submittedAt: $entity->getSubmittedAt(),
            confirmedAt: $entity->getConfirmedAt(),
            lines: $lines,
            version: $entity->getVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
