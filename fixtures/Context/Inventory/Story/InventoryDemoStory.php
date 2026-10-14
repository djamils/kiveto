<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Inventory\Story;

use App\Context\Inventory\Application\Command\ArchiveStockItem\ArchiveStockItem;
use App\Context\Inventory\Application\Command\ConsumeStock\ConsumeStock;
use App\Context\Inventory\Application\Command\ExpireOutdatedLots\ExpireOutdatedLots;
use App\Context\Inventory\Application\Command\MarkLotAsRecalled\MarkLotAsRecalled;
use App\Context\Inventory\Application\Command\OpenStockItem\OpenStockItem;
use App\Context\Inventory\Application\Command\ReceiveStock\ReceiveStock;
use App\Context\Inventory\Application\Command\RecordPhysicalInventory\RecordPhysicalInventory;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\StockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThreshold;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThresholdType;
use App\Context\Inventory\Domain\StockItem\ValueObject\StorageLocation;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Procurement\Story\ProcurementDemoStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Demo stock of Paris and Lyon, built on the articles their catalogue tracks
 * and the shelves of the stock-v-topbar mock-up: comfortable stock, articles at
 * or under their threshold, two ruptures, lots expiring within weeks, a lot
 * past its date still on the shelf, a lot recalled by its maker, an article
 * tracked but never received, and an article whose follow-up was stopped.
 * Clinique des Calanques follows nothing: its stock page is empty.
 *
 * Every lot is received on the day it arrived and used up week after week
 * through the command bus — consultations, counter sales, a breakage, a
 * count — so the trail, the weekly outflows, the coverage and the register of
 * controlled substances all read like a real one.
 */
final class InventoryDemoStory extends Story
{
    /**
     * Article code => [storage location, alert threshold, used per 30 days, lots].
     * A lot is [number, expiry (days from today), left today, received (days
     * from today), what happens to it: 'ok', 'depleted' or 'recalled'].
     * Quantities are in the article's unit of measure.
     *
     * @var array<string, array{0: string, 1: int, 2: float, 3: list<array{0: string, 1: int, 2: int, 3: int, 4: string}>}>
     */
    private const array SHELVES = [
        'APOQUEL-36'     => ['PHARMACY', 60, 70, [['LOT-A2611', 246, 86, -50, 'ok']]],
        'BAYTRIL-10'     => ['PHARMACY', 2, 1, [['LOT-B0914', 276, 3, -101, 'ok']]],
        'CATH-22G'       => ['OPERATING_ROOM', 50, 60, [['LOT-026012', 306, 90, -79, 'ok'], ['LOT-026107', 507, 50, -10, 'ok']]],
        'CERENIA-10'     => ['PHARMACY', 2, 2, [['LOT-026013', 336, 4, -62, 'ok']]],
        'COLLAR-E'       => ['RECEPTION', 10, 9, [['LOT-026014', 1228, 22, -85, 'ok']]],
        'COMPRESSE'      => ['OPERATING_ROOM', 5, 4, [['LOT-026015', 396, 9, -67, 'ok']]],
        'CONVENIA-80'    => ['FRIDGE', 2, 2, [['LOT-026020', 186, 1, -44, 'ok']]],
        'CYSTAID-PLUS'   => ['RECEPTION', 30, 120, [['LOT-026021', 156, 0, -80, 'depleted']]],
        'FIL-SUT-30'     => ['OPERATING_ROOM', 24, 18, [['LOT-025114', 15, 10, -280, 'ok'], ['LOT-026022', 326, 25, -39, 'ok']]],
        'FOOD-HILLS-KD'  => ['RECEPTION', 12, 12, [['LOT-025090', -6, 4, -353, 'ok'], ['LOT-026023', 206, 4, -51, 'ok']]],
        'FOOD-JUNIOR'    => ['RECEPTION', 24, 60, [['LOT-026024', 266, 84, -52, 'ok'], ['LOT-026025', 306, 24, -31, 'recalled']]],
        'FOOD-RC'        => ['RECEPTION', 24, 32, [['LOT-RC551', 150, 0, -93, 'depleted']]],
        'FRONTLINE-CB'   => ['RECEPTION', 18, 20, [['LOT-F3302', 557, 42, -27, 'ok']]],
        'KETAMINE-100'   => ['NARCOTICS_SAFE', 1, 1.2, [['LOT-K7731', 466, 3, -64, 'ok']]],
        'NARKETAN-10'    => ['NARCOTICS_SAFE', 1, 0.6, [['LOT-N1188', 375, 2, -121, 'ok']]],
        'MEDETO-1'       => ['OPERATING_ROOM', 1, 1, [['LOT-M4410', 420, 2, -83, 'ok']]],
        'MELOXIDYL-1'    => ['PHARMACY', 4, 6, [['LOT-X2207', 191, 11, -42, 'ok']]],
        'OXYTETRA-200'   => ['PHARMACY', 1, 0.5, [['LOT-O0712', 467, 2, -171, 'ok']]],
        'SERINGUE-5'     => ['OPERATING_ROOM', 100, 140, [['LOT-S8800', 1409, 310, -71, 'ok']]],
        'SHAMPOING-DERM' => ['RECEPTION', 3, 2, [['LOT-SH201', 446, 6, -128, 'ok']]],
        'STRONGHOLD-45'  => ['RECEPTION', 12, 9, [['LOT-ST414', 405, 24, -49, 'ok']]],
        'SYNULOX-500'    => ['PHARMACY', 50, 60, [['LOT-SY330', 52, 38, -224, 'ok'], ['LOT-SY912', 376, 80, -15, 'ok']]],
        'TERRAMYCIN-LA'  => ['PHARMACY', 1, 0.4, [['LOT-T0388', 364, 2, -185, 'ok']]],
        'VETMEDIN-125'   => ['PHARMACY', 0, 0, []],
    ];

    /** The day the clinic counted its shelves, and what the count corrected. */
    private const int COUNT_DAY = -20;

    /** @var array<string, int> article code => counted minus expected */
    private const array COUNT_GAPS = ['COMPRESSE' => -1, 'SERINGUE-5' => 6];

    private const string RECALL_REASON = 'Rappel fabricant — défaut de fermeture des sacs';

    private int $seed = 7;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
        private readonly StockItemRepositoryInterface $stockItems,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        foreach ([ClinicDataStory::INDEPENDENT_CLINIC_ID, ClinicDataStory::GROUP_CLINIC_ID] as $clinicId) {
            $this->buildFor($clinicId);
        }
    }

    private function buildFor(string $clinicId): void
    {
        $articles = $this->articles($clinicId);

        if ([] === $articles) {
            return;
        }

        $this->seed = 7;
        $staff      = $this->staff($clinicId);
        $patients   = $this->patients($clinicId);
        $suppliers  = $this->suppliers();

        foreach (self::SHELVES as $code => [$location, $threshold, $monthly, $lots]) {
            if (!isset($articles[$code])) {
                continue;
            }

            $article     = $articles[$code];
            $stockItemId = $this->open($clinicId, $article, $location, $threshold);
            $supplier    = $suppliers[ProcurementDemoStory::OFFERS[$code][0]] ?? null;
            $gap         = self::COUNT_GAPS[$code] ?? 0;

            foreach ($lots as $index => [$number, $expiry, $left, $received, $fate]) {
                $used = $this->used($monthly, $received, $expiry, \count($lots), $fate);

                // The count found more or less than expected: what was used
                // around it is what makes the shelf end where it is today.
                $initial = max($left + $used - (0 === $index ? $gap : 0), 1);

                if ('recalled' === $fate) {
                    $initial = $left + $used;
                }

                $lotId = $this->receive($clinicId, $stockItemId, $article, $number, $initial, $expiry, $received, $supplier, $staff['assistant']);
                $this->useUp($clinicId, $stockItemId, $article, $lotId, $initial - ('recalled' === $fate ? $left : $left - (0 === $index ? $gap : 0)), $received, min($expiry, 0), $location, $staff, $patients);

                if ('recalled' === $fate) {
                    $this->commandBus->dispatch(new MarkLotAsRecalled(
                        stockItemId: $stockItemId,
                        clinicId: $clinicId,
                        lotId: $lotId,
                        reason: self::RECALL_REASON,
                        performedBy: $staff['vets'][0] ?? null,
                    ));
                }
            }

            if (0 !== $gap) {
                $this->count($clinicId, $stockItemId, $article, $gap, $staff['assistant']);
            }

            if ('MELOXIDYL-1' === $code) {
                $this->consume($clinicId, $stockItemId, $article, $this->lastLotOf($stockItemId), 1, -23, 'LOSS_BREAKAGE', null, 'Flacon tombé au comptoir', $staff['assistant']);
            }
        }

        // What is past its date leaves the usable stock, as the nightly job
        // would have done; the lot stays on the shelf until someone bins it.
        $this->commandBus->dispatch(new ExpireOutdatedLots($clinicId));

        if (ClinicDataStory::INDEPENDENT_CLINIC_ID === $clinicId) {
            $this->stopped($clinicId, $staff['assistant']);
        }
    }

    /**
     * How much of a lot went out: the article's monthly use over the time the
     * lot was on the shelf (75 days at most), halved when two lots share it.
     */
    private function used(float $monthly, int $received, int $expiry, int $lots, string $fate): int
    {
        if ('recalled' === $fate) {
            return 12;
        }

        $days = min(-$received, 75, max(1, $expiry - $received));
        $used = (int) round($monthly / 30 * $days * ($lots > 1 ? 0.5 : 1));

        return 'depleted' === $fate ? max($used, 2) : $used;
    }

    /**
     * @param array{id: string, unit: string, name: string} $article
     */
    private function open(string $clinicId, array $article, string $location, int $threshold): string
    {
        $stockItemId = $this->commandBus->dispatch(new OpenStockItem(
            clinicId: $clinicId,
            articleId: $article['id'],
            unit: $article['unit'],
            thresholdAmount: \sprintf('%d.000000', $threshold),
            thresholdUnit: $article['unit'],
            thresholdType: 'ABSOLUTE',
            trackStock: true,
            location: $location,
        ));

        \assert(\is_string($stockItemId));

        return $stockItemId;
    }

    /**
     * @param array{id: string, unit: string, name: string} $article
     */
    private function receive(
        string $clinicId,
        string $stockItemId,
        array $article,
        string $number,
        int $quantity,
        int $expiry,
        int $received,
        ?string $supplier,
        ?string $by,
    ): string {
        $day = $this->day($received)->setTime(9, 30);

        $this->commandBus->dispatch(new ReceiveStock(
            stockItemId: $stockItemId,
            clinicId: $clinicId,
            lotNumber: $number,
            quantityAmount: \sprintf('%d.000000', $quantity),
            quantityUnit: $article['unit'],
            expiryDate: $this->day($expiry)->format('Y-m-d'),
            sourceSupplierId: $supplier,
            occurredAt: $day->format(\DateTimeInterface::ATOM),
            reference: 'BL-' . $day->format('Y') . '-' . mb_substr($number, -3),
            performedBy: $by,
        ));

        return $this->lastLotOf($stockItemId);
    }

    /**
     * Takes a lot down by `$total`, in a handful of movements spread between
     * its receipt and today (or its expiry, if earlier).
     *
     * @param array{id: string, unit: string, name: string}     $article
     * @param array{vets: list<string>, assistant: string|null} $staff
     * @param list<string>                                      $patients
     */
    private function useUp(
        string $clinicId,
        string $stockItemId,
        array $article,
        string $lotId,
        int $total,
        int $from,
        int $until,
        string $location,
        array $staff,
        array $patients,
    ): void {
        if ($total <= 0) {
            return;
        }

        $span  = max(1, min($until - $from, 75));
        $start = max($from, $until - $span);
        $count = min(6, max(1, $total));
        $left  = $total;

        for ($i = 0; $i < $count && $left > 0; ++$i) {
            $quantity = $i === $count - 1 ? $left : max(1, intdiv($total, $count));
            $left -= $quantity;
            $offset = $start + (int) round(($i + 1) / ($count + 1) * ($until - $start));

            // The front desk sells what it holds; the rest goes into consultations.
            $sale    = 'RECEPTION' === $location && $this->random() < 0.7;
            $patient = [] !== $patients ? $patients[(int) floor($this->random() * \count($patients))] : null;
            $vet     = [] !== $staff['vets'] ? $staff['vets'][(int) floor($this->random() * \count($staff['vets']))] : null;

            $this->consume(
                clinicId: $clinicId,
                stockItemId: $stockItemId,
                article: $article,
                lotId: $lotId,
                quantity: $quantity,
                day: min($offset, 0),
                reason: $sale ? 'SALE_RETAIL' : 'CONSUMPTION_CONSULTATION',
                reference: $sale ? null : $patient,
                note: 'NARCOTICS_SAFE' === $location && null !== $vet ? $this->vetName($vet) : null,
                by: $sale ? $staff['assistant'] : $vet,
            );
        }
    }

    /**
     * @param array{id: string, unit: string, name: string} $article
     */
    private function consume(
        string $clinicId,
        string $stockItemId,
        array $article,
        string $lotId,
        int $quantity,
        int $day,
        string $reason,
        ?string $reference,
        ?string $note,
        ?string $by,
    ): void {
        $this->commandBus->dispatch(new ConsumeStock(
            stockItemId: $stockItemId,
            clinicId: $clinicId,
            quantityAmount: \sprintf('%d.000000', $quantity),
            quantityUnit: $article['unit'],
            occurredAt: $this->day($day)->setTime(8 + (int) floor($this->random() * 11), (int) floor($this->random() * 12) * 5)->format(\DateTimeInterface::ATOM),
            reference: $reference,
            performedBy: $by,
            note: $note,
            reason: $reason,
            lotId: $lotId,
        ));
    }

    /**
     * @param array{id: string, unit: string, name: string} $article
     */
    private function count(string $clinicId, string $stockItemId, array $article, int $gap, ?string $by): void
    {
        $lot = $this->connection->fetchAssociative(
            "SELECT BIN_TO_UUID(id) AS id, quantity_amount FROM inventory__lots
             WHERE stock_item_id = UUID_TO_BIN(:id) AND status = 'ACTIVE'
             ORDER BY expiry_date ASC LIMIT 1",
            ['id' => $stockItemId],
        );

        if (false === $lot) {
            return;
        }

        $day = $this->day(self::COUNT_DAY)->setTime(18, 15);

        $this->commandBus->dispatch(new RecordPhysicalInventory(
            stockItemId: $stockItemId,
            clinicId: $clinicId,
            lotId: RowAccessor::string($lot, 'id'),
            newLotQuantityAmount: \sprintf('%d.000000', (int) RowAccessor::string($lot, 'quantity_amount') + $gap),
            newLotQuantityUnit: $article['unit'],
            reason: 'PHYSICAL_INVENTORY',
            occurredAt: $day->format(\DateTimeInterface::ATOM),
            performedBy: $by,
            reference: 'INV-' . $day->format('Y-m'),
        ));
    }

    /**
     * The old soft collar: received, sold out, then no longer followed. The
     * catalogue archived the article long ago, so the item is opened straight
     * through the domain rather than through a command that would refuse it.
     */
    private function stopped(string $clinicId, ?string $by): void
    {
        $article = $this->archivedArticle($clinicId, 'OLD-COLLAR');

        if (null === $article) {
            return;
        }

        $stockItemId = Uuid::v7()->toRfc4122();
        $unit        = UnitOfMeasure::fromString($article['unit']);

        $this->stockItems->save(StockItem::open(
            id: StockItemId::fromString($stockItemId),
            clinicId: ClinicId::fromString($clinicId),
            articleId: ArticleId::fromString($article['id']),
            unit: $unit,
            threshold: new StockThreshold(Quantity::of('0.000000', $unit), StockThresholdType::ABSOLUTE),
            trackStock: true,
            clock: $this->clock,
            location: StorageLocation::RECEPTION,
        ));

        $lotId = $this->receive($clinicId, $stockItemId, $article, 'LOT-024410', 20, 200, -400, null, $by);
        $this->consume($clinicId, $stockItemId, $article, $lotId, 20, -330, 'SALE_RETAIL', null, null, $by);
        $this->commandBus->dispatch(new ArchiveStockItem($stockItemId, $clinicId));
    }

    private function day(int $offset): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today')->modify(\sprintf('%+d days', $offset));
    }

    /** A deterministic sequence, so the demo is the same from one load to the next. */
    private function random(): float
    {
        $this->seed = ($this->seed * 16807) % 2147483647;

        return $this->seed / 2147483647;
    }

    private function lastLotOf(string $stockItemId): string
    {
        $lotId = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(id) FROM inventory__lots
             WHERE stock_item_id = UUID_TO_BIN(:stockItemId)
             ORDER BY created_at DESC, id DESC
             LIMIT 1',
            ['stockItemId' => $stockItemId],
        );

        \assert(\is_string($lotId));

        return $lotId;
    }

    /**
     * @return array<string, string> supplier code => id
     */
    private function suppliers(): array
    {
        $suppliers = [];

        foreach ($this->connection->fetchAllAssociative('SELECT BIN_TO_UUID(id) AS id, code FROM procurement__suppliers') as $row) {
            $suppliers[RowAccessor::string($row, 'code')] = RowAccessor::string($row, 'id');
        }

        return $suppliers;
    }

    /**
     * @return array{vets: list<string>, assistant: string|null}
     */
    private function staff(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(user_id) AS user_id, role FROM clinic__clinic_memberships
             WHERE clinic_id = UUID_TO_BIN(:clinicId) AND status = 'ACTIVE'
               AND role IN ('VETERINARY', 'VETERINARY_ASSISTANT')
             ORDER BY created_at_utc ASC, user_id ASC",
            ['clinicId' => $clinicId],
        );

        $staff = ['vets' => [], 'assistant' => null];

        foreach ($rows as $row) {
            if ('VETERINARY' === RowAccessor::string($row, 'role')) {
                $staff['vets'][] = RowAccessor::string($row, 'user_id');
            } else {
                $staff['assistant'] ??= RowAccessor::string($row, 'user_id');
            }
        }

        return $staff;
    }

    private function vetName(string $userId): ?string
    {
        $name = $this->connection->fetchOne(
            'SELECT p.display_name FROM clinic__staff_profiles p
             JOIN clinic__clinic_memberships m ON m.id = p.membership_id
             WHERE m.user_id = UUID_TO_BIN(:userId) LIMIT 1',
            ['userId' => $userId],
        );

        return \is_string($name) ? 'Dr ' . $name : null;
    }

    /**
     * « Rocky (Bernard) » for the clinic's patients: what a consultation
     * leaves as the reference of what it used.
     *
     * @return list<string>
     */
    private function patients(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT a.name, c.last_name FROM animal__animals a
             JOIN animal__ownerships o ON o.animal_id = a.id AND o.status = 'ACTIVE'
             JOIN client__clients c ON c.id = o.client_id
             WHERE a.clinic_id = UUID_TO_BIN(:clinicId)
             ORDER BY a.created_at ASC, a.id ASC
             LIMIT 12",
            ['clinicId' => $clinicId],
        );

        return array_map(
            static fn (array $row): string => \sprintf('%s (%s)', RowAccessor::string($row, 'name'), RowAccessor::string($row, 'last_name')),
            $rows,
        );
    }

    /**
     * The articles this clinic tracks, by code.
     *
     * @return array<string, array{id: string, unit: string, name: string}>
     */
    private function articles(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(id) AS id, code, name, unit_of_measure
             FROM catalog__articles
             WHERE tenant_id = :clinicId AND track_stock = 1 AND status = 'ACTIVE'
             ORDER BY code ASC",
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        $articles = [];

        foreach ($rows as $row) {
            $articles[RowAccessor::string($row, 'code')] = [
                'id'   => RowAccessor::string($row, 'id'),
                'unit' => RowAccessor::string($row, 'unit_of_measure'),
                'name' => RowAccessor::string($row, 'name'),
            ];
        }

        return $articles;
    }

    /**
     * @return array{id: string, unit: string, name: string}|null
     */
    private function archivedArticle(string $clinicId, string $code): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT BIN_TO_UUID(id) AS id, name, unit_of_measure FROM catalog__articles
             WHERE tenant_id = :clinicId AND code = :code AND status = 'ARCHIVED'",
            ['clinicId' => Uuid::fromString($clinicId)->toBinary(), 'code' => $code],
        );

        if (false === $row) {
            return null;
        }

        return [
            'id'   => RowAccessor::string($row, 'id'),
            'unit' => RowAccessor::string($row, 'unit_of_measure'),
            'name' => RowAccessor::string($row, 'name'),
        ];
    }
}
