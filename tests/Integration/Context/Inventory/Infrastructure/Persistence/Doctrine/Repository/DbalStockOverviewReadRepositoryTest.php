<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockItemEntity;
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

    // ── The whole clinic ──

    public function testEveryItemOfTheClinicComesBackWithItsPlace(): void
    {
        $kept = Uuid::v7()->toRfc4122();
        StockItemEntityFactory::new()->withClinicId($this->clinicId)->withArticleId($kept)->create(['storageLocation' => 'FRIDGE']);
        StockItemEntityFactory::new()->withClinicId($this->clinicId)->archived()->create();
        StockItemEntityFactory::new()->withClinicId($this->otherClinicId)->create();

        $items = $this->stock->snapshotsOfClinic($this->clinic());

        self::assertCount(2, $items);
        self::assertSame('FRIDGE', $items[$kept]->location);
    }

    public function testALotSaysWhereItCameFromAndWhyItWasRecalled(): void
    {
        $articleId = Uuid::v7()->toRfc4122();
        $item      = $this->stockItem($articleId);
        $received  = LotEntityFactory::new()->create(['stockItem' => $item, 'receivedAt' => new \DateTimeImmutable('2026-07-02 09:30:00')])->_real();
        $recalled  = LotEntityFactory::new()->create(['stockItem' => $item, 'status' => 'RECALLED', 'quantityAmount' => '0.000000'])->_real();

        $this->movement($articleId, 'IN', '20.000000', '-30 days', ['lotId' => $received->getId(), 'reference' => 'BL-2026-611', 'reason' => 'RECEIPT_SUPPLIER']);
        $this->movement($articleId, 'OUT', '4.000000', '-1 day', ['lotId' => $recalled->getId(), 'reason' => 'RECALL_RETURN_TO_SUPPLIER', 'note' => 'Rappel fabricant']);

        $lots = [];

        \assert($item instanceof StockItemEntity);
        $itemId = $item->getId()->toRfc4122();

        foreach ($this->stock->lotsFor($this->clinic(), [$itemId])[$itemId] as $lot) {
            $lots[$lot->status] = $lot;
        }

        self::assertSame('BL-2026-611', $lots['ACTIVE']->reference);
        self::assertSame('2026-07-02 09:30:00', $lots['ACTIVE']->receivedAt);
        self::assertNull($lots['ACTIVE']->recallReason);
        self::assertSame('Rappel fabricant', $lots['RECALLED']->recallReason);
        self::assertNotNull($lots['RECALLED']->changedAt);
    }

    public function testTheOutflowsAreCountedWeekByWeekAndTheUseOverThirtyDays(): void
    {
        $articleId = Uuid::v7()->toRfc4122();
        $today     = new \DateTimeImmutable('today 12:00');

        $this->movement($articleId, 'OUT', '3.000000', 'today 09:00', ['reason' => 'CONSUMPTION_CONSULTATION']);
        $this->movement($articleId, 'OUT', '2.000000', '-8 days', ['reason' => 'SALE_RETAIL']);
        $this->movement($articleId, 'OUT', '5.000000', '-9 days', ['reason' => 'LOSS_BREAKAGE']);
        $this->movement($articleId, 'OUT', '7.000000', '-40 days', ['reason' => 'SALE_RETAIL']);
        $this->movement($articleId, 'IN', '50.000000', '-2 days', ['reason' => 'RECEIPT_SUPPLIER']);
        $this->movement($articleId, 'OUT', '9.000000', '-200 days', ['reason' => 'SALE_RETAIL']);

        $outflow = $this->stock->outflows($this->clinic(), $today, 12)[$articleId];

        self::assertCount(12, $outflow->weekly);
        self::assertSame('3.000000', $outflow->weekly[11]);
        self::assertSame('7.000000', $outflow->weekly[10]);
        self::assertSame('7.000000', $outflow->weekly[6]);
        self::assertSame('5.000000', $outflow->usedLastThirtyDays);
        self::assertSame([], $this->stock->outflows(ClinicId::fromString($this->otherClinicId), $today, 12));
    }

    public function testTheLatestMovementsOfEachArticleAreKeptNewestFirst(): void
    {
        $first  = Uuid::v7()->toRfc4122();
        $second = Uuid::v7()->toRfc4122();

        foreach (['-4 days', '-3 days', '-2 days'] as $when) {
            $this->movement($first, 'OUT', '1.000000', $when, ['reason' => 'SALE_RETAIL']);
        }

        $this->movement($second, 'IN', '5.000000', '-1 day', ['reason' => 'RECEIPT_SUPPLIER']);

        $latest = $this->stock->latestMovements($this->clinic(), 2);

        self::assertCount(2, $latest[$first]);
        self::assertSame('-2 days', $this->ago($latest[$first][0]->occurredAt));
        self::assertCount(1, $latest[$second]);
    }

    public function testTheLastCountIsTheLatestPhysicalInventory(): void
    {
        self::assertNull($this->stock->lastCountAt($this->clinic()));

        $articleId = Uuid::v7()->toRfc4122();
        $this->movement($articleId, 'ADJUSTMENT', '1.000000', '-20 days', ['reason' => 'PHYSICAL_INVENTORY']);
        $this->movement($articleId, 'ADJUSTMENT', '1.000000', '-2 days', ['reason' => 'CORRECTION']);

        self::assertSame(
            new \DateTimeImmutable('-20 days')->format('Y-m-d'),
            $this->stock->lastCountAt($this->clinic())?->format('Y-m-d'),
        );
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

    public function testTheTrailCarriesTheBalanceAndTheSenseOfAnAdjustment(): void
    {
        $articleId = Uuid::v7()->toRfc4122();

        $this->movement($articleId, 'IN', '10.000000', '-5 days', ['reason' => 'RECEIPT_SUPPLIER']);
        $this->movement($articleId, 'OUT', '3.000000', '-4 days', ['reason' => 'SALE_RETAIL']);
        $this->movement($articleId, 'ADJUSTMENT', '1.000000', '-3 days', ['reason' => 'PHYSICAL_INVENTORY', 'decrease' => true]);
        $this->movement($articleId, 'ADJUSTMENT', '2.000000', '-2 days', ['reason' => 'CORRECTION']);

        $rows = $this->stock->movements($this->clinic(), new MovementCriteria())['items'];

        self::assertSame(['8.000000', '6.000000', '7.000000', '10.000000'], array_map(static fn ($row): ?string => $row->balance, $rows));
        self::assertSame('-1.000000', $rows[1]->quantity);
        self::assertFalse($rows[1]->isIncoming());

        // A narrowing keeps the balance of the whole trail.
        $outs = $this->stock->movements($this->clinic(), new MovementCriteria(types: ['OUT']))['items'];
        self::assertSame('7.000000', $outs[0]->balance);
    }

    public function testTheTrailIsNarrowedByReasonAndNoLotsAreReadForNoItem(): void
    {
        $articleId = Uuid::v7()->toRfc4122();

        $this->movement($articleId, 'OUT', '1.000000', '-2 days', ['reason' => 'SALE_RETAIL']);
        $this->movement($articleId, 'OUT', '1.000000', '-1 day', ['reason' => 'CONSUMPTION_CONSULTATION', 'reference' => 'Rex (Dupont)']);

        $consultations = $this->stock->movements($this->clinic(), new MovementCriteria(reasons: ['CONSUMPTION_CONSULTATION']));

        self::assertSame(1, $consultations['total']);
        self::assertSame('Rex (Dupont)', $consultations['items'][0]->reference);
        self::assertSame([], $this->stock->lotsFor($this->clinic(), []));
    }

    public function testTheTrailIsSearchedOnLotReferenceNoteAndTheArticlesTheCatalogueNamed(): void
    {
        $articleId = Uuid::v7()->toRfc4122();
        $named     = Uuid::v7()->toRfc4122();
        $item      = $this->stockItem($articleId);
        $lot       = LotEntityFactory::new()->create(['stockItem' => $item, 'lotNumber' => 'LOT-SY330'])->_real();

        $this->movement($articleId, 'OUT', '1.000000', '-3 days', ['lotId' => $lot->getId(), 'reason' => 'SALE_RETAIL']);
        $this->movement($articleId, 'OUT', '1.000000', '-2 days', ['reference' => 'Rocky (Bernard)', 'reason' => 'CONSUMPTION_CONSULTATION']);
        $this->movement($articleId, 'OUT', '1.000000', '-1 day', ['note' => 'Flacon tombé au comptoir', 'reason' => 'LOSS_BREAKAGE']);
        $this->movement($named, 'IN', '5.000000', '-1 day', ['reason' => 'RECEIPT_SUPPLIER']);

        self::assertSame(1, $this->search('SY330'));
        self::assertSame(1, $this->search('rocky'));
        self::assertSame(1, $this->search('tombé'));
        self::assertSame(1, $this->search('apoquel', [$named]));
        self::assertSame(0, $this->search('100%'));
        self::assertSame(1, $this->stock->movements($this->clinic(), new MovementCriteria(lotId: $lot->getId()->toRfc4122()))['total']);
    }

    /**
     * @param list<string> $articleIds
     */
    private function search(string $term, array $articleIds = []): int
    {
        return $this->stock->movements($this->clinic(), new MovementCriteria(term: $term, termArticleIds: $articleIds))['total'];
    }

    private function ago(\DateTimeImmutable $when): string
    {
        return \sprintf('%+d days', (int) new \DateTimeImmutable('today')->diff($when->setTime(0, 0))->format('%r%a'));
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

    /**
     * @param array<string, mixed> $extra
     */
    private function movement(string $articleId, string $type, string $quantity, string $when, array $extra = []): void
    {
        StockMovementEntityFactory::new()->create([
            'clinicId'       => Uuid::fromString($this->clinicId),
            'articleId'      => Uuid::fromString($articleId),
            'type'           => $type,
            'quantityAmount' => $quantity,
            'occurredAt'     => new \DateTimeImmutable($when),
        ] + $extra);
    }

    private function clinic(): ClinicId
    {
        return ClinicId::fromString($this->clinicId);
    }
}
