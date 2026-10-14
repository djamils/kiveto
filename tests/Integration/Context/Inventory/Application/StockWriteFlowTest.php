<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Inventory\Application;

use App\Context\Inventory\Application\Command\ChangeStockThreshold\ChangeStockThreshold;
use App\Context\Inventory\Application\Command\ConsumeStock\ConsumeStock;
use App\Context\Inventory\Application\Command\OpenStockItem\OpenStockItem;
use App\Context\Inventory\Application\Command\ReceiveStock\ReceiveStock;
use App\Context\Inventory\Application\Query\ListStockItemsByClinic\ListStockItemsByClinic;
use App\Context\Inventory\Application\Query\ListStockItemsByClinic\StockItemSummaryView;
use App\Fixtures\Context\Catalog\Factory\ArticleEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * Several commands on the same stock item, in one process — which is what a
 * fixture load, a batch import or a message worker does. Each one has to land on
 * the same row, and the alerts they raise have to be storable.
 */
final class StockWriteFlowTest extends KernelTestCase
{
    use Factories;

    private CommandBusInterface $commandBus;
    private Connection $connection;
    private string $clinicId;
    private string $articleId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $commandBus = $container->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $this->commandBus = $commandBus;

        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;

        $this->clinicId = Uuid::v7()->toRfc4122();

        // The stock item is opened against a real catalogue article: the domain
        // refuses to track something the clinic does not sell.
        $articleId = Uuid::v7();

        ArticleEntityFactory::new()->forClinic($this->clinicId)->create([
            'id'         => $articleId,
            'code'       => 'STK-TEST',
            'name'       => 'Article de test',
            'trackStock' => true,
        ]);

        $this->articleId = $articleId->toRfc4122();
    }

    public function testAStockItemCanBeWrittenSeveralTimesInOneProcess(): void
    {
        $stockItemId = $this->open();

        $this->commandBus->dispatch(new ReceiveStock(
            stockItemId: $stockItemId,
            clinicId: $this->clinicId,
            lotNumber: 'LOT-A',
            quantityAmount: '20.000000',
            quantityUnit: 'UNIT',
            expiryDate: (new \DateTimeImmutable('+200 days'))->format('Y-m-d'),
            sourceSupplierId: null,
            occurredAt: null,
            reference: 'BL-1',
            performedBy: null,
        ));

        $this->commandBus->dispatch(new ConsumeStock(
            stockItemId: $stockItemId,
            clinicId: $this->clinicId,
            quantityAmount: '5.000000',
            quantityUnit: 'UNIT',
            occurredAt: null,
            reference: null,
            performedBy: null,
            note: null,
            reason: 'CONSUMPTION_CONSULTATION',
        ));

        $this->commandBus->dispatch(new ChangeStockThreshold(
            stockItemId: $stockItemId,
            clinicId: $this->clinicId,
            thresholdAmount: '8.000000',
            thresholdUnit: 'UNIT',
            thresholdType: 'ABSOLUTE',
        ));

        $row = $this->connection->fetchAssociative(
            'SELECT total_on_hand_amount, threshold_amount FROM inventory__stock_items WHERE id = UUID_TO_BIN(:id)',
            ['id' => $stockItemId],
        );

        self::assertIsArray($row);
        self::assertSame('15.000000', $row['total_on_hand_amount']);
        self::assertSame('8.000000', $row['threshold_amount']);

        self::assertSame(1, $this->countLots($stockItemId), 'the lot is updated in place, not duplicated');
    }

    public function testTheClinicStockListShowsWhatIsAvailable(): void
    {
        $stockItemId = $this->open();
        $this->receive($stockItemId, 'LOT-A', '10.000000');

        $queryBus = self::getContainer()->get(QueryBusInterface::class);
        \assert($queryBus instanceof QueryBusInterface);

        $all = $queryBus->ask(new ListStockItemsByClinic($this->clinicId, null, 1000));
        self::assertIsArray($all);
        self::assertCount(1, $all);
        self::assertInstanceOf(StockItemSummaryView::class, $all[0]);
        self::assertSame($this->articleId, $all[0]->articleId);
        self::assertSame(10.0, (float) $all[0]->availableQuantityAmount);
        self::assertTrue($all[0]->trackStock);
        self::assertSame(1, $all[0]->activeLotCount);

        $archived = $queryBus->ask(new ListStockItemsByClinic($this->clinicId, 'ARCHIVED'));
        self::assertSame([], $archived);
    }

    public function testASecondReceiptAddsItsOwnLotToTheSameItem(): void
    {
        $stockItemId = $this->open();

        $this->receive($stockItemId, 'LOT-A', '10.000000');
        $this->receive($stockItemId, 'LOT-B', '4.000000');

        self::assertSame(2, $this->countLots($stockItemId));
    }

    public function testALotAboutToExpireRaisesAnAlertThatCanBeStored(): void
    {
        $stockItemId = $this->open();

        $this->commandBus->dispatch(new ReceiveStock(
            stockItemId: $stockItemId,
            clinicId: $this->clinicId,
            lotNumber: 'LOT-SOON',
            quantityAmount: '6.000000',
            quantityUnit: 'UNIT',
            expiryDate: (new \DateTimeImmutable('+5 days'))->format('Y-m-d'),
            sourceSupplierId: null,
            occurredAt: null,
            reference: null,
            performedBy: null,
        ));

        $alert = $this->connection->fetchAssociative(
            "SELECT severity, current_level_amount, current_level_unit, earliest_expiry
             FROM inventory__stock_alerts
             WHERE stock_item_id = UUID_TO_BIN(:id) AND type = 'LOW_EXPIRY'",
            ['id' => $stockItemId],
        );

        self::assertIsArray($alert);
        self::assertSame('CRITICAL', $alert['severity']);
        self::assertNotNull($alert['earliest_expiry']);
        // An expiry alert carries no level, so it carries no unit either — the
        // table refuses one without the other.
        self::assertNull($alert['current_level_amount']);
        self::assertNull($alert['current_level_unit']);
    }

    private function open(): string
    {
        $stockItemId = $this->commandBus->dispatch(new OpenStockItem(
            clinicId: $this->clinicId,
            articleId: $this->articleId,
            unit: 'UNIT',
            thresholdAmount: '2.000000',
            thresholdUnit: 'UNIT',
            thresholdType: 'ABSOLUTE',
            trackStock: true,
        ));

        self::assertIsString($stockItemId);

        return $stockItemId;
    }

    private function receive(string $stockItemId, string $lotNumber, string $quantity): void
    {
        $this->commandBus->dispatch(new ReceiveStock(
            stockItemId: $stockItemId,
            clinicId: $this->clinicId,
            lotNumber: $lotNumber,
            quantityAmount: $quantity,
            quantityUnit: 'UNIT',
            expiryDate: (new \DateTimeImmutable('+150 days'))->format('Y-m-d'),
            sourceSupplierId: null,
            occurredAt: null,
            reference: null,
            performedBy: null,
        ));
    }

    private function countLots(string $stockItemId): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM inventory__lots WHERE stock_item_id = UUID_TO_BIN(:id)',
            ['id' => $stockItemId],
        );

        self::assertIsNumeric($count);

        return (int) $count;
    }
}
