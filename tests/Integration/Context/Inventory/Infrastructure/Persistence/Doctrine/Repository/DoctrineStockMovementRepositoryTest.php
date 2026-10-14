<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockMovement\Repository\StockMovementRepositoryInterface;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Context\Inventory\Domain\StockMovement\ValueObject\StockMovementId;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * A movement goes to the trail and comes back as it was written — the sense
 * of an adjustment included, since its quantity is always positive.
 */
final class DoctrineStockMovementRepositoryTest extends KernelTestCase
{
    public function testAMovementComesBackWithTheSenseOfItsAdjustment(): void
    {
        $container = self::getContainer();

        $movements = $container->get(StockMovementRepositoryInterface::class);
        \assert($movements instanceof StockMovementRepositoryInterface);

        $em = $container->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $id  = StockMovementId::fromString(Uuid::v7()->toRfc4122());
        $now = new \DateTimeImmutable('2026-08-21 10:00:00');

        $movements->save(StockMovement::record(
            id: $id,
            clinicId: ClinicId::fromString(Uuid::v7()->toRfc4122()),
            articleId: ArticleId::fromString(Uuid::v7()->toRfc4122()),
            lotId: LotId::fromString(Uuid::v7()->toRfc4122()),
            type: MovementType::ADJUSTMENT,
            reason: MovementReason::PHYSICAL_INVENTORY,
            quantity: Quantity::of('2.000000', UnitOfMeasure::fromString('UNIT')),
            occurredAt: $now,
            reference: 'INV-2026-08-21',
            performedBy: null,
            note: null,
            createdAt: $now,
            decrease: true,
        ));
        $em->clear();

        $back = $movements->findById($id);

        self::assertNotNull($back);
        self::assertTrue($back->isDecrease());
        self::assertSame('INV-2026-08-21', $back->reference());
        self::assertSame('2.000000', $back->quantity()->amount());
        self::assertNotNull($back->lotId());
    }
}
