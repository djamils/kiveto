<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierReceipt\Entity\SupplierReceiptLine;
use App\Context\Procurement\Domain\SupplierReceipt\SupplierReceipt;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\DeliveryNoteReference;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\LotInformation;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\ReceiptMatchType;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptLineId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptStatus;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierReceiptEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierReceiptLineEntity;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Uid\Uuid;

final readonly class SupplierReceiptMapper
{
    public function toEntity(SupplierReceipt $receipt): SupplierReceiptEntity
    {
        $entity = new SupplierReceiptEntity();

        $entity->setId(Uuid::fromString($receipt->id()->toString()));
        $entity->setClinicId(Uuid::fromString($receipt->clinicId()->toString()));
        $entity->setSupplierId(Uuid::fromString($receipt->supplierId()->toString()));
        $entity->setPurchaseOrderId(Uuid::fromString($receipt->purchaseOrderId()->toString()));
        $entity->setDeliveryNoteReference($receipt->deliveryNoteReference()->toString());
        $entity->setMatchType($receipt->matchType()->value);
        $entity->setStatus($receipt->status()->value);
        $entity->setValidatedAt($receipt->validatedAt());
        $entity->setReceivedBy(
            null !== $receipt->receivedBy()
                ? Uuid::fromString($receipt->receivedBy())
                : null
        );
        $entity->setComment($receipt->comment());
        $entity->setCreatedAt($receipt->createdAt());
        $entity->setUpdatedAt($receipt->updatedAt());

        $entity->clearLines();

        foreach ($receipt->lines() as $line) {
            $lineEntity = new SupplierReceiptLineEntity();
            $lineEntity->setId(Uuid::fromString($line->id()->toString()));
            $lineEntity->setPurchaseOrderLineId(Uuid::fromString($line->purchaseOrderLineId()->toString()));
            $lineEntity->setArticleId(Uuid::fromString($line->articleId()->toString()));
            $lineEntity->setReceivedAmount($line->receivedAmount());
            $lineEntity->setReceivedUnit($line->receivedUnit()->toString());

            $lot = $line->lotInformation();
            if (null !== $lot) {
                $lineEntity->setLotNumber($lot->lotNumber);
                $lineEntity->setLotExpiryDate($lot->expiryDate);
                $lineEntity->setLotManufacturedAt($lot->manufacturedAt);
            } else {
                $lineEntity->setLotNumber(null);
                $lineEntity->setLotExpiryDate(null);
                $lineEntity->setLotManufacturedAt(null);
            }

            $actualPrice = $line->actualUnitPrice();
            if (null !== $actualPrice) {
                $lineEntity->setActualUnitPriceMinor($actualPrice->minorUnits());
                $lineEntity->setActualUnitPriceCurrency($actualPrice->currency()->toString());
            } else {
                $lineEntity->setActualUnitPriceMinor(null);
                $lineEntity->setActualUnitPriceCurrency(null);
            }

            $lineEntity->setNote($line->note());

            $entity->addLine($lineEntity);
        }

        return $entity;
    }

    public function toDomain(SupplierReceiptEntity $entity): SupplierReceipt
    {
        $lines = [];
        foreach ($entity->getLines() as $lineEntity) {
            $lotInformation = null;
            $lotNumber      = $lineEntity->getLotNumber();
            $lotExpiryDate  = $lineEntity->getLotExpiryDate();

            if (null !== $lotNumber && null !== $lotExpiryDate) {
                $lotInformation = LotInformation::create(
                    $lotNumber,
                    $lotExpiryDate,
                    $lineEntity->getLotManufacturedAt(),
                );
            }

            $actualUnitPrice = null;
            $priceMinor      = $lineEntity->getActualUnitPriceMinor();
            $priceCurrency   = $lineEntity->getActualUnitPriceCurrency();

            if (null !== $priceMinor && null !== $priceCurrency) {
                $actualUnitPrice = Money::fromMinorUnits(
                    $priceMinor,
                    CurrencyCode::fromString($priceCurrency),
                );
            }

            $lines[] = SupplierReceiptLine::reconstitute(
                id: SupplierReceiptLineId::fromString($lineEntity->getId()->toString()),
                purchaseOrderLineId: PurchaseOrderLineId::fromString($lineEntity->getPurchaseOrderLineId()->toString()),
                articleId: ArticleId::fromString($lineEntity->getArticleId()->toString()),
                receivedAmount: $lineEntity->getReceivedAmount(),
                receivedUnit: UnitOfMeasure::fromString($lineEntity->getReceivedUnit()),
                lotInformation: $lotInformation,
                actualUnitPrice: $actualUnitPrice,
                note: $lineEntity->getNote(),
            );
        }

        return SupplierReceipt::reconstitute(
            id: SupplierReceiptId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toString()),
            supplierId: SupplierId::fromString($entity->getSupplierId()->toString()),
            purchaseOrderId: PurchaseOrderId::fromString($entity->getPurchaseOrderId()->toString()),
            deliveryNoteReference: DeliveryNoteReference::fromString($entity->getDeliveryNoteReference()),
            matchType: ReceiptMatchType::from($entity->getMatchType()),
            status: SupplierReceiptStatus::from($entity->getStatus()),
            validatedAt: $entity->getValidatedAt(),
            receivedBy: $entity->getReceivedBy()?->toString(),
            comment: $entity->getComment(),
            lines: $lines,
            version: $entity->getVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
