<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\PurchaseOrderEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\PurchaseOrderLineEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierAccountEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierCatalogEntryEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierPricingEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierReceiptEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierReceiptLineEntity;
use PHPUnit\Framework\TestCase;

final class EntityAccessorsTest extends TestCase
{
    public function testPurchaseOrderEntitySetVersion(): void
    {
        $entity = new PurchaseOrderEntity();
        $entity->setVersion(42);

        self::assertSame(42, $entity->getVersion());
    }

    public function testPurchaseOrderLineEntityGetPurchaseOrder(): void
    {
        $parent = new PurchaseOrderEntity();
        $line   = new PurchaseOrderLineEntity();
        $line->setPurchaseOrder($parent);

        self::assertSame($parent, $line->getPurchaseOrder());
    }

    public function testSupplierEntitySetVersion(): void
    {
        $entity = new SupplierEntity();
        $entity->setVersion(7);

        self::assertSame(7, $entity->getVersion());
    }

    public function testSupplierAccountEntitySetVersion(): void
    {
        $entity = new SupplierAccountEntity();
        $entity->setVersion(3);

        self::assertSame(3, $entity->getVersion());
    }

    public function testSupplierCatalogEntryEntitySetVersion(): void
    {
        $entity = new SupplierCatalogEntryEntity();
        $entity->setVersion(11);

        self::assertSame(11, $entity->getVersion());
    }

    public function testSupplierPricingEntitySetVersion(): void
    {
        $entity = new SupplierPricingEntity();
        $entity->setVersion(5);

        self::assertSame(5, $entity->getVersion());
    }

    public function testSupplierReceiptEntitySetVersion(): void
    {
        $entity = new SupplierReceiptEntity();
        $entity->setVersion(9);

        self::assertSame(9, $entity->getVersion());
    }

    public function testSupplierReceiptLineEntityGetSupplierReceipt(): void
    {
        $parent = new SupplierReceiptEntity();
        $line   = new SupplierReceiptLineEntity();
        $line->setSupplierReceipt($parent);

        self::assertSame($parent, $line->getSupplierReceipt());
    }
}
