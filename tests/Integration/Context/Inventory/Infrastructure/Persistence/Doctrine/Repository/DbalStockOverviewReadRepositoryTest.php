<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Application\Query\StockOverview\StockStateFilter;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Fixtures\Context\Inventory\Factory\LotEntityFactory;
use App\Fixtures\Context\Inventory\Factory\StockItemEntityFactory;
use App\Fixtures\Context\Inventory\Factory\StockMovementEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The read side of the stock screens against a real database: the figures have
 * to be exact, the states have to narrow, and the trail has to paginate.
 */
final class DbalStockOverviewReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private StockOverviewReadRepositoryInterface $stock;
    private string $clinicId;
    private string $otherClinicId;

    protected function setUp(): void
    {
        parent::setUp();

        $stock = self::getContainer()->get(StockOverviewReadRepositoryInterface::class);
        \assert($stock instanceof StockOverviewReadRepositoryInterface);
        $this->stock = $stock;

        $this->clinicId      = Uuid::v7()->toRfc4122();
        $this->otherClinicId = Uuid::v7()->toRfc4122();
    }

    // ── Snapshot ──

    public function testTheFiguresOfAnArticleComeBackAsTheyAreHeld(): void
    {
        $articleId = Uuid::v7()->toRfc4122();

        $item = $this->stockItem($articleId, onHand: '25.000000', reserved: '4.000000', threshold: '10.000000');
        $this->lot($item, '15.000000', '+120 days');
        $this->lot($item, '10.000000', '+300 days');

        $snapshot = $this->stock->snapshotFor($this->clinic(), [$articleId]);

        self::assertArrayHasKey($articleId, $snapshot);

        $row = $snapshot[$articleId];

        self::assertSame('25.000000', $row->onHand);
        self::assertSame('4.000000', $row->reserved);
        self::assertSame('21.000000', $row->available);
        self::assertSame('10.000000', $row->threshold);
        self::assertSame(2, $row->activeLotCount);
        self::assertSame(0, $row->expiredLotCount);
        self::assertFalse($row->isOutOfStock());
        self::assertFalse($row->isLow());
        self::assertFalse($row->isArchived());
        self::assertNotNull($row->earliestExpiry);
    }

    public function testAnArticleWithoutAStockItemIsSimplyAbsent(): void
    {
        $known   = Uuid::v7()->toRfc4122();
        $unknown = Uuid::v7()->toRfc4122();

        $this->stockItem($known);

        $snapshot = $this->stock->snapshotFor($this->clinic(), [$known, $unknown]);

        self::assertArrayHasKey($known, $snapshot);
        self::assertArrayNotHasKey($unknown, $snapshot);
    }

    public function testAskingForNothingReadsNothing(): void
    {
        self::assertSame([], $this->stock->snapshotFor($this->clinic(), []));
    }

    public function testTheStockOfAnotherClinicIsInvisible(): void
    {
        $articleId = Uuid::v7()->toRfc4122();
        $this->stockItem($articleId);

        self::assertSame(
            [],
            $this->stock->snapshotFor(ClinicId::fromString($this->otherClinicId), [$articleId]),
        );
    }

    public function testTheEarliestExpiryIsTheOneThatMatters(): void
    {
        $articleId = Uuid::v7()->toRfc4122();
        $item      = $this->stockItem($articleId);

        $this->lot($item, '5.000000', '+300 days');
        $this->lot($item, '5.000000', '+12 days');

        $row = $this->stock->snapshotFor($this->clinic(), [$articleId])[$articleId];

        $expected = (new \DateTimeImmutable('+12 days'))->format('Y-m-d');

        self::assertSame($expected, $row->earliestExpiry);
        self::assertTrue($row->expiresWithin(30, new \DateTimeImmutable('today')));
        self::assertFalse($row->expiresWithin(5, new \DateTimeImmutable('today')));
    }

    public function testAnItemWithNoLotHasNoExpiryToWatch(): void
    {
        $articleId = Uuid::v7()->toRfc4122();
        $this->stockItem($articleId);

        $row = $this->stock->snapshotFor($this->clinic(), [$articleId])[$articleId];

        self::assertNull($row->earliestExpiry);
        self::assertFalse($row->expiresWithin(30, new \DateTimeImmutable('today')));
        self::assertFalse($row->hasExpiredLots());
        self::assertFalse($row->hasRecalledLots());
    }

    public function testExpiredAndRecalledLotsAreCountedApart(): void
    {
        $articleId = Uuid::v7()->toRfc4122();
        $item      = $this->stockItem($articleId);

        $this->lot($item, '5.000000', '+90 days');
        LotEntityFactory::new()->expired()->create(['stockItem' => $item]);
        LotEntityFactory::new()->create(['stockItem' => $item, 'status' => 'RECALLED']);

        $row = $this->stock->snapshotFor($this->clinic(), [$articleId])[$articleId];

        self::assertSame(1, $row->activeLotCount);
        self::assertSame(1, $row->expiredLotCount);
        self::assertSame(1, $row->recalledLotCount);
        self::assertTrue($row->hasExpiredLots());
        self::assertTrue($row->hasRecalledLots());
    }

    public function testWhatIsReservedIsNotAvailable(): void
    {
        $articleId = Uuid::v7()->toRfc4122();
        $this->stockItem($articleId, onHand: '4.000000', reserved: '4.000000', threshold: '2.000000');

        $row = $this->stock->snapshotFor($this->clinic(), [$articleId])[$articleId];

        self::assertSame('0.000000', $row->available);
        self::assertTrue($row->isOutOfStock());
        self::assertFalse($row->isLow());
    }

    public function testAnUntrackedArticleIsNeverOutOfStock(): void
    {
        $articleId = Uuid::v7()->toRfc4122();

        StockItemEntityFactory::new()
            ->withClinicId($this->clinicId)
            ->withArticleId($articleId)
            ->noTracking()
            ->withTotalOnHand('0.000000', 'UNIT')
            ->create(['thresholdAmount' => '5.000000', 'reservedAmount' => '0.000000'])
        ;

        $row = $this->stock->snapshotFor($this->clinic(), [$articleId])[$articleId];

        self::assertFalse($row->trackStock);
        self::assertFalse($row->isOutOfStock());
        self::assertFalse($row->isLow());
    }

    // ── States ──

    public function testTheArticlesOutOfStockAreListed(): void
    {
        $out   = Uuid::v7()->toRfc4122();
        $stood = Uuid::v7()->toRfc4122();

        $this->stockItem($out, onHand: '0.000000', reserved: '0.000000', threshold: '5.000000');
        $this->stockItem($stood, onHand: '20.000000', reserved: '0.000000', threshold: '5.000000');

        $ids = $this->stock->articleIdsInState($this->clinic(), StockStateFilter::OUT_OF_STOCK, 30);

        self::assertSame([$out], $ids);
    }

    public function testTheArticlesBelowTheirThresholdAreListedWithoutTheEmptyOnes(): void
    {
        $low         = Uuid::v7()->toRfc4122();
        $out         = Uuid::v7()->toRfc4122();
        $stood       = Uuid::v7()->toRfc4122();
        $noThreshold = Uuid::v7()->toRfc4122();

        $this->stockItem($low, onHand: '4.000000', reserved: '0.000000', threshold: '5.000000');
        $this->stockItem($out, onHand: '0.000000', reserved: '0.000000', threshold: '5.000000');
        $this->stockItem($stood, onHand: '20.000000', reserved: '0.000000', threshold: '5.000000');
        $this->stockItem($noThreshold, onHand: '1.000000', reserved: '0.000000', threshold: '0.000000');

        $ids = $this->stock->articleIdsInState($this->clinic(), StockStateFilter::LOW, 30);

        self::assertSame([$low], $ids);
    }

    public function testTheArticlesExpiringWithinTheWindowAreListed(): void
    {
        $soon  = Uuid::v7()->toRfc4122();
        $later = Uuid::v7()->toRfc4122();

        $this->lot($this->stockItem($soon), '5.000000', '+10 days');
        $this->lot($this->stockItem($later), '5.000000', '+200 days');

        self::assertSame([$soon], $this->stock->articleIdsInState($this->clinic(), StockStateFilter::EXPIRING, 30));
        self::assertSame([], $this->stock->articleIdsInState($this->clinic(), StockStateFilter::EXPIRING, 5));
    }

    public function testTheArticlesHoldingAnExpiredOrRecalledLotAreListed(): void
    {
        $expired  = Uuid::v7()->toRfc4122();
        $recalled = Uuid::v7()->toRfc4122();

        LotEntityFactory::new()->expired()->create(['stockItem' => $this->stockItem($expired)]);
        LotEntityFactory::new()->create(['stockItem' => $this->stockItem($recalled), 'status' => 'RECALLED']);

        self::assertSame([$expired], $this->stock->articleIdsInState($this->clinic(), StockStateFilter::EXPIRED, 30));
        self::assertSame([$recalled], $this->stock->articleIdsInState($this->clinic(), StockStateFilter::RECALLED, 30));
    }

    public function testTheStatesOfAnotherClinicAreInvisible(): void
    {
        $this->stockItem(Uuid::v7()->toRfc4122(), onHand: '0.000000', reserved: '0.000000', threshold: '5.000000');

        self::assertSame([], $this->stock->articleIdsInState(
            ClinicId::fromString($this->otherClinicId),
            StockStateFilter::OUT_OF_STOCK,
            30,
        ));
    }

    // ── Movements ──

    public function testTheTrailComesBackNewestFirstAndPaginated(): void
    {
        $articleId = Uuid::v7()->toRfc4122();

        foreach (['-3 days', '-2 days', '-1 day'] as $index => $when) {
            $this->movement($articleId, 'RECEIPT', (string) (10 + $index) . '.000000', $when);
        }

        $firstPage = $this->stock->movements($this->clinic(), new MovementCriteria(perPage: 2));

        self::assertSame(3, $firstPage['total']);
        self::assertCount(2, $firstPage['items']);
        self::assertSame('12.000000', $firstPage['items'][0]->quantity);
        self::assertTrue($firstPage['items'][0]->isIncoming());

        $secondPage = $this->stock->movements($this->clinic(), new MovementCriteria(page: 2, perPage: 2));

        self::assertCount(1, $secondPage['items']);
        self::assertSame('10.000000', $secondPage['items'][0]->quantity);
    }

    public function testTheTrailIsNarrowedByTypeAndPeriodAndArticle(): void
    {
        $first  = Uuid::v7()->toRfc4122();
        $second = Uuid::v7()->toRfc4122();

        $this->movement($first, 'RECEIPT', '10.000000', '-10 days');
        $this->movement($first, 'CONSUMPTION', '-2.000000', '-1 day');
        $this->movement($second, 'RECEIPT', '5.000000', '-1 day');

        $byType = $this->stock->movements($this->clinic(), new MovementCriteria(types: ['CONSUMPTION']));
        self::assertSame(1, $byType['total']);
        self::assertFalse($byType['items'][0]->isIncoming());

        $byArticle = $this->stock->movements($this->clinic(), new MovementCriteria(articleIds: [$second]));
        self::assertSame(1, $byArticle['total']);

        $byPeriod = $this->stock->movements($this->clinic(), new MovementCriteria(
            from: new \DateTimeImmutable('-2 days'),
        ));
        self::assertSame(2, $byPeriod['total']);

        $upTo = $this->stock->movements($this->clinic(), new MovementCriteria(
            to: new \DateTimeImmutable('-5 days'),
        ));
        self::assertSame(1, $upTo['total']);
    }

    public function testANarrowingToNoArticleReadsNothing(): void
    {
        $this->movement(Uuid::v7()->toRfc4122(), 'RECEIPT', '10.000000', '-1 day');

        self::assertSame(
            ['items' => [], 'total' => 0],
            $this->stock->movements($this->clinic(), new MovementCriteria(articleIds: [])),
        );
    }

    public function testAClinicWithoutMovementsHasAnEmptyTrail(): void
    {
        self::assertSame(
            ['items' => [], 'total' => 0],
            $this->stock->movements($this->clinic(), new MovementCriteria()),
        );
    }

    public function testAMovementCarriesItsLotNumberWhenItHasOne(): void
    {
        $articleId = Uuid::v7()->toRfc4122();
        $item      = $this->stockItem($articleId);
        $lot       = LotEntityFactory::new()->create(['stockItem' => $item, 'lotNumber' => 'LOT-4242']);

        StockMovementEntityFactory::new()->create([
            'clinicId'       => Uuid::fromString($this->clinicId),
            'articleId'      => Uuid::fromString($articleId),
            'lotId'          => $lot->_real()->getId(),
            'type'           => 'RECEIPT',
            'quantityAmount' => '6.000000',
            'occurredAt'     => new \DateTimeImmutable('-1 hour'),
        ]);

        $row = $this->stock->movements($this->clinic(), new MovementCriteria())['items'][0];

        self::assertSame('LOT-4242', $row->lotNumber);
        self::assertNotNull($row->lotId);
    }

    private function stockItem(
        string $articleId,
        string $onHand = '10.000000',
        string $reserved = '0.000000',
        string $threshold = '2.000000',
    ): object {
        return StockItemEntityFactory::new()
            ->withClinicId($this->clinicId)
            ->withArticleId($articleId)
            ->withTotalOnHand($onHand, 'UNIT')
            ->create([
                'reservedAmount'  => $reserved,
                'thresholdAmount' => $threshold,
                'thresholdUnit'   => 'UNIT',
            ])
            ->_real()
        ;
    }

    private function lot(object $stockItem, string $quantity, string $expiry): void
    {
        LotEntityFactory::new()->create([
            'stockItem'      => $stockItem,
            'quantityAmount' => $quantity,
            'expiryDate'     => new \DateTimeImmutable($expiry),
            'status'         => 'ACTIVE',
        ]);
    }

    private function movement(string $articleId, string $type, string $quantity, string $when): void
    {
        StockMovementEntityFactory::new()->create([
            'clinicId'       => Uuid::fromString($this->clinicId),
            'articleId'      => Uuid::fromString($articleId),
            'type'           => $type,
            'quantityAmount' => $quantity,
            'occurredAt'     => new \DateTimeImmutable($when),
        ]);
    }

    private function clinic(): ClinicId
    {
        return ClinicId::fromString($this->clinicId);
    }
}
