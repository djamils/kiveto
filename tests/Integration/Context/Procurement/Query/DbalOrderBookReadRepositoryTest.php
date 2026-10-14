<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Query;

use App\Context\Procurement\Application\Port\OrderBookReadRepositoryInterface;
use App\Context\Procurement\Application\Query\OrderBook\OpenPurchaseOrder;
use App\Context\Procurement\Application\Query\OrderBook\SupplyOffer;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository\DbalOrderBookReadRepository;
use App\Fixtures\Context\Procurement\Factory\PurchaseOrderEntityFactory;
use App\Fixtures\Context\Procurement\Factory\PurchaseOrderLineEntityFactory;
use App\Fixtures\Context\Procurement\Factory\SupplierAccountEntityFactory;
use App\Fixtures\Context\Procurement\Factory\SupplierCatalogEntryEntityFactory;
use App\Fixtures\Context\Procurement\Factory\SupplierEntityFactory;
use App\Fixtures\Context\Procurement\Factory\SupplierPricingEntityFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * What the stock page reads of Procurement, against a real database: who
 * sells an article to the clinic and at what price, and the orders under way.
 */
#[CoversClass(DbalOrderBookReadRepository::class)]
#[CoversClass(SupplyOffer::class)]
#[CoversClass(OpenPurchaseOrder::class)]
final class DbalOrderBookReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private OrderBookReadRepositoryInterface $orderBook;

    private string $clinicId;

    protected function setUp(): void
    {
        parent::setUp();

        $orderBook = self::getContainer()->get(OrderBookReadRepositoryInterface::class);
        \assert($orderBook instanceof OrderBookReadRepositoryInterface);
        $this->orderBook = $orderBook;
        $this->clinicId  = Uuid::v7()->toRfc4122();
    }

    public function testAnOfferIsFoundByBarcodeOrReferenceAtTheClinicsPrice(): void
    {
        $supplier = $this->supplier('Centravet');
        $account  = SupplierAccountEntityFactory::new()->create(['clinicId' => Uuid::fromString($this->clinicId), 'supplierId' => $supplier])->_real();

        $byGtin = SupplierCatalogEntryEntityFactory::new()->create([
            'supplierId'        => $supplier, 'productCode' => 'APOQUEL-36-' . uniqid(), 'gtin' => '03661103046394',
            'catalogPriceMinor' => 2740, 'packagingUnit' => 'BOX', 'packagingAmount' => '20',
        ])->_real();
        $code   = 'CATH-' . uniqid();
        $byCode = SupplierCatalogEntryEntityFactory::new()->create(['supplierId' => $supplier, 'productCode' => $code, 'catalogPriceMinor' => 95])->_real();
        SupplierCatalogEntryEntityFactory::new()->create(['supplierId' => $supplier, 'productCode' => 'OLD-' . uniqid(), 'gtin' => '03661103046394', 'status' => 'DISCONTINUED']);

        SupplierPricingEntityFactory::new()->create([
            'clinicId' => Uuid::fromString($this->clinicId), 'supplierId' => $supplier, 'catalogEntryId' => $byGtin->getId(), 'amountMinor' => 2500,
        ]);
        SupplierPricingEntityFactory::new()->create([
            'clinicId'  => Uuid::fromString($this->clinicId), 'supplierId' => $supplier, 'catalogEntryId' => $byCode->getId(), 'amountMinor' => 10,
            'expiresAt' => new \DateTimeImmutable('-1 day'),
        ]);

        $offers = [];

        foreach ($this->orderBook->supplyOffers($this->clinicId, ['03661103046394'], [$code]) as $offer) {
            $offers[$offer->catalogEntryId] = $offer;
        }

        self::assertCount(2, $offers);

        $apoquel = $offers[$byGtin->getId()->toRfc4122()];
        self::assertSame(2500, $apoquel->priceMinor);
        self::assertSame('EUR', $apoquel->currency);
        self::assertSame('BOX', $apoquel->packagingUnit);
        self::assertSame('20', $apoquel->packagingAmount);
        self::assertSame('Centravet', $apoquel->supplierName);
        self::assertSame($account->getId()->toRfc4122(), $apoquel->supplierAccountId);

        // An expired negotiation falls back on the catalogue price.
        self::assertSame(95, $offers[$byCode->getId()->toRfc4122()]->priceMinor);
    }

    public function testOnlyTheSuppliersTheClinicHasAnActiveAccountWithAreAsked(): void
    {
        $code     = 'COLLAR-' . uniqid();
        $disabled = $this->supplier('Alcyon');
        $foreign  = $this->supplier('Coveto');

        SupplierAccountEntityFactory::new()->create(['clinicId' => Uuid::fromString($this->clinicId), 'supplierId' => $disabled, 'status' => 'DISABLED']);
        SupplierAccountEntityFactory::new()->create(['clinicId' => Uuid::v7(), 'supplierId' => $foreign]);
        SupplierCatalogEntryEntityFactory::new()->create(['supplierId' => $disabled, 'productCode' => $code]);
        SupplierCatalogEntryEntityFactory::new()->create(['supplierId' => $foreign, 'productCode' => $code . '-B']);

        self::assertSame([], $this->orderBook->supplyOffers($this->clinicId, [], [$code, $code . '-B']));
        self::assertSame([], $this->orderBook->supplyOffers($this->clinicId, [], []));
    }

    public function testTheOpenOrdersComeWithTheirLinesOldestFirst(): void
    {
        $supplier = $this->supplier('Alcyon');
        $clinic   = Uuid::fromString($this->clinicId);

        $sent = PurchaseOrderEntityFactory::new()->create([
            'clinicId'    => $clinic, 'supplierId' => $supplier, 'status' => 'SUBMITTED',
            'submittedAt' => new \DateTimeImmutable('-2 days'), 'createdAt' => new \DateTimeImmutable('-3 days'),
        ])->_real();
        $draft = PurchaseOrderEntityFactory::new()->create(['clinicId' => $clinic, 'supplierId' => $supplier, 'status' => 'DRAFT'])->_real();
        PurchaseOrderEntityFactory::new()->create(['clinicId' => $clinic, 'supplierId' => $supplier, 'status' => 'CLOSED']);
        PurchaseOrderEntityFactory::new()->create(['clinicId' => Uuid::v7(), 'supplierId' => $supplier, 'status' => 'DRAFT']);

        PurchaseOrderLineEntityFactory::new()->forOrder($sent)->create(['orderedAmount' => '50', 'orderedUnit' => 'UNIT', 'receivedAmount' => '10', 'unitPriceMinor' => 95]);
        PurchaseOrderLineEntityFactory::new()->forOrder($sent)->create(['status' => 'CANCELLED']);

        $orders = $this->orderBook->openOrders($this->clinicId);

        self::assertCount(2, $orders);
        self::assertSame($sent->getId()->toRfc4122(), $orders[0]->id);
        self::assertFalse($orders[0]->isDraft());
        self::assertSame('Alcyon', $orders[0]->supplierName);
        self::assertNotNull($orders[0]->submittedAt);
        self::assertCount(2, $orders[0]->lines);

        $lines = [];

        foreach ($orders[0]->lines as $line) {
            $lines[$line->status] = $line;
        }

        self::assertSame('50', $lines['ACTIVE']->orderedAmount);
        self::assertSame('10', $lines['ACTIVE']->receivedAmount);
        self::assertSame(95, $lines['ACTIVE']->unitPriceMinor);
        self::assertSame('UNIT', $lines['ACTIVE']->orderedUnit);

        self::assertSame($draft->getId()->toRfc4122(), $orders[1]->id);
        self::assertTrue($orders[1]->isDraft());
        self::assertSame([], $orders[1]->lines);
    }

    public function testAnOrderSentByFileNamesTheFileToHandOver(): void
    {
        $byFile    = SupplierEntityFactory::new()->create(['name' => 'Coveto', 'integrationMode' => 'MANUAL_EXPORT', 'adapterIdentifier' => 'generic_csv'])->_real()->getId();
        $simulated = $this->supplier('Centravet');
        $clinic    = Uuid::fromString($this->clinicId);

        PurchaseOrderEntityFactory::new()->create(['clinicId' => $clinic, 'supplierId' => $byFile, 'status' => 'SUBMITTED', 'externalReferenceValue' => '2026/08/21/generic-order-PO-1.csv']);
        PurchaseOrderEntityFactory::new()->create(['clinicId' => $clinic, 'supplierId' => $simulated, 'status' => 'SUBMITTED', 'externalReferenceValue' => 'SIM-123']);

        $files = array_map(static fn (OpenPurchaseOrder $order): ?string => $order->exportFile, $this->orderBook->openOrders($this->clinicId));
        sort($files);

        self::assertSame([null, '2026/08/21/generic-order-PO-1.csv'], $files);
    }

    public function testAClinicWithoutOrdersHasNone(): void
    {
        self::assertSame([], $this->orderBook->openOrders($this->clinicId));
    }

    private function supplier(string $name): Uuid
    {
        return SupplierEntityFactory::new()->create(['name' => $name])->_real()->getId();
    }
}
