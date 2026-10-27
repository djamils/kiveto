<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Infrastructure\Projection;

use App\Context\Inventory\Application\Port\StockAlertReadRepositoryInterface;
use App\Context\Inventory\Application\Port\StockNoticeJobsInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockAlert\ReadModel\StockAlertView;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertType;
use App\Context\Inventory\Domain\StockItem\Event\LotAdded;
use App\Context\Inventory\Domain\StockItem\Event\LotDepleted;
use App\Context\Inventory\Domain\StockItem\Event\LotExpired;
use App\Context\Inventory\Domain\StockItem\Event\LotRecalled;
use App\Context\Inventory\Domain\StockItem\Event\StockAdjusted;
use App\Context\Inventory\Domain\StockItem\Event\StockConsumed;
use App\Context\Inventory\Domain\StockItem\Event\StockReceived;
use App\Context\Inventory\Domain\StockItem\Event\StockThresholdChanged;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Infrastructure\Projection\StockAlertProjector;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The live projection of the stock alerts, on a scripted reading of the
 * shelves: each event re-evaluates its alerts, and a newly inserted stock-out
 * queues its « Stock critique » bell notice (D13) — once per alert row.
 */
final class StockAlertProjectorTest extends TestCase
{
    private const string ITEM    = '01960000-0000-7000-8000-0000000a2001';
    private const string CLINIC  = '01960000-0000-7000-8000-0000000c0001';
    private const string ARTICLE = '01960000-0000-7000-8000-0000000a3001';

    /** @var array<string, array{track: int, onHand: string, reserved: string, threshold: string, minExpiry: ?string, missing: list<string>}> */
    private array $items = [];

    /** @var array<string, string|\Throwable|null> */
    private array $articleNames = [self::ARTICLE => 'Vermifuge Milbemax'];

    /** @var list<StockAlertView> */
    private array $alerts = [];

    /** @var list<array{string, string}> */
    private array $notices = [];

    private StockAlertProjector $projector;

    protected function setUp(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturnCallback($this->fetchOne(...));
        $connection->method('fetchAssociative')->willReturnCallback($this->fetchAssociative(...));

        $this->projector = new StockAlertProjector($connection, $this->alertRepository(), $this->jobs());
    }

    public function testAStockOutIsAnnouncedOncePerAlertRow(): void
    {
        $this->item(onHand: '0.000000');

        $this->projector->onStockConsumed(new StockConsumed(self::ITEM, self::CLINIC, self::ARTICLE, 'lot-1', '1', 'TABLET', 'now'));

        self::assertSame([
            [StockAlertType::LOW_STOCK, StockAlertSeverity::WARNING, '0.000000', 'TABLET', 'Vermifuge Milbemax', null],
            [StockAlertType::OUT_OF_STOCK, StockAlertSeverity::CRITICAL, '0.000000', 'TABLET', 'Vermifuge Milbemax', null],
        ], $this->summary());
        self::assertSame([[self::CLINIC, $this->alerts[1]->alertId]], $this->notices);

        // Still out of stock: the alerts are refreshed, nothing new is announced.
        $this->projector->onStockAdjusted(new StockAdjusted(self::ITEM, self::CLINIC, self::ARTICLE, 'lot-1', '0', 'TABLET', '0', 'COUNT', 'now'));
        self::assertCount(2, $this->alerts);
        self::assertCount(1, $this->notices);

        // Restocked: both resolved.
        $this->item(onHand: '10.000000');
        $this->projector->onStockReceived(new StockReceived(self::ITEM, self::CLINIC, self::ARTICLE, 'lot-2', 'L2', '10', 'TABLET', '2027-01-01', null, 'now'));
        self::assertNotNull($this->alerts[0]->resolvedAt);
        self::assertNotNull($this->alerts[1]->resolvedAt);

        // Out of stock again: a new alert row, a new notice.
        $this->item(onHand: '2.000000', reserved: '2.000000');
        $this->projector->onLotDepleted(new LotDepleted(self::ITEM, 'lot-2', 'L2'));
        self::assertCount(3, $this->alerts);
        self::assertSame([StockAlertType::OUT_OF_STOCK, null, '0.000000'], [$this->alerts[2]->type, $this->alerts[2]->resolvedAt, $this->alerts[2]->currentLevelAmount]);
        self::assertSame([[self::CLINIC, $this->alerts[1]->alertId], [self::CLINIC, $this->alerts[2]->alertId]], $this->notices);
    }

    public function testTheExpiryAlertFollowsTheEarliestActiveLot(): void
    {
        $this->item(minExpiry: '+3 days');
        $this->projector->onLotAdded(new LotAdded(self::ITEM, 'lot-1', 'L1', '5', 'TABLET', '2026-11-01', null));
        self::assertSame([[StockAlertType::LOW_EXPIRY, StockAlertSeverity::CRITICAL, null, null, 'Vermifuge Milbemax', null]], $this->summary());

        $this->item(minExpiry: '+10 days');
        $this->projector->onLotExpired(new LotExpired(self::ITEM, self::CLINIC, 'lot-1', 'L1', '5', 'TABLET', '2026-11-01'));
        self::assertSame(StockAlertSeverity::WARNING, $this->alerts[0]->severity);

        $this->item(minExpiry: '+20 days');
        $this->projector->onLotRecalled(new LotRecalled(self::ITEM, self::CLINIC, 'lot-1', 'L1', '5', 'TABLET', 'Rappel fabricant', 'now'));
        self::assertSame(StockAlertSeverity::INFO, $this->alerts[0]->severity);

        $this->item(minExpiry: '+60 days');
        $this->projector->onLotAdded(new LotAdded(self::ITEM, 'lot-2', 'L2', '5', 'TABLET', '2027-01-01', null));
        self::assertNotNull($this->alerts[0]->resolvedAt);

        // Expiry alerts are never « Stock critique ».
        self::assertSame([], $this->notices);
    }

    public function testTheThresholdDecidesTheLowStockAlert(): void
    {
        $this->item(onHand: '1.000000', threshold: '2.000000');
        $this->articleNames[self::ARTICLE] = new \RuntimeException('catalogue unreachable');

        $this->projector->onStockThresholdChanged(new StockThresholdChanged(self::ITEM, self::CLINIC, self::ARTICLE, '2', 'TABLET', 'ABSOLUTE'));

        self::assertSame([[StockAlertType::LOW_STOCK, StockAlertSeverity::WARNING, '1.000000', 'TABLET', '', null]], $this->summary());
        self::assertSame([], $this->notices);

        $this->articleNames[self::ARTICLE] = null;
        $this->item(onHand: '0.000000', threshold: '2.000000');
        $this->projector->onStockReceived(new StockReceived(self::ITEM, self::CLINIC, self::ARTICLE, null, 'L1', '0', 'TABLET', '2027-01-01', null, 'now'));
        self::assertSame('', $this->alerts[1]->articleName);
    }

    public function testAnUntrackedOrUnknownItemIsLeftAlone(): void
    {
        $this->item(onHand: '0.000000', track: 0);
        $this->everyEvent(self::ITEM);
        $this->everyEvent('01960000-0000-7000-8000-0000000a2999');

        self::assertSame([], $this->alerts);
    }

    public function testAnItemGoneMeanwhileRaisesNothing(): void
    {
        $this->item(onHand: '0.000000', minExpiry: '+3 days', missing: ['MIN(expiry_date)', 'threshold_amount FROM', 'reserved_amount FROM']);
        $this->everyEvent(self::ITEM);

        $this->item(onHand: '0.000000', minExpiry: '+3 days', missing: ['clinic_id, article_id']);
        $this->everyEvent(self::ITEM);

        self::assertSame([], $this->alerts);
    }

    /**
     * @return list<StockAlertView>
     */
    public function rows(): array
    {
        return $this->alerts;
    }

    public function store(StockAlertView $alert): void
    {
        foreach ($this->alerts as $i => $existing) {
            if ($existing->alertId === $alert->alertId) {
                $this->alerts[$i] = $alert;

                return;
            }
        }

        $this->alerts[] = $alert;
    }

    public function notice(string $clinicId, string $alertId): void
    {
        $this->notices[] = [$clinicId, $alertId];
    }

    private function everyEvent(string $item): void
    {
        $this->projector->onLotAdded(new LotAdded($item, 'lot-1', 'L1', '5', 'TABLET', '2026-11-01', null));
        $this->projector->onStockReceived(new StockReceived($item, self::CLINIC, self::ARTICLE, 'lot-1', 'L1', '5', 'TABLET', '2026-11-01', null, 'now'));
        $this->projector->onStockConsumed(new StockConsumed($item, self::CLINIC, self::ARTICLE, 'lot-1', '1', 'TABLET', 'now'));
        $this->projector->onStockAdjusted(new StockAdjusted($item, self::CLINIC, self::ARTICLE, 'lot-1', '0', 'TABLET', '0', 'COUNT', 'now'));
        $this->projector->onLotExpired(new LotExpired($item, self::CLINIC, 'lot-1', 'L1', '5', 'TABLET', '2026-11-01'));
        $this->projector->onLotDepleted(new LotDepleted($item, 'lot-1', 'L1'));
        $this->projector->onStockThresholdChanged(new StockThresholdChanged($item, self::CLINIC, self::ARTICLE, '2', 'TABLET', 'ABSOLUTE'));
        $this->projector->onLotRecalled(new LotRecalled($item, self::CLINIC, 'lot-1', 'L1', '5', 'TABLET', 'Rappel', 'now'));
    }

    /**
     * @param list<string> $missing fragments of the queries that find no row
     */
    private function item(string $onHand = '5.000000', string $reserved = '0.000000', string $threshold = '2.000000', ?string $minExpiry = null, int $track = 1, array $missing = []): void
    {
        $this->items[self::ITEM] = [
            'track'     => $track,
            'onHand'    => $onHand,
            'reserved'  => $reserved,
            'threshold' => $threshold,
            'minExpiry' => null !== $minExpiry ? new \DateTimeImmutable($minExpiry)->format('Y-m-d') : null,
            'missing'   => $missing,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function fetchOne(string $sql, array $params = []): mixed
    {
        if (str_contains($sql, 'catalog__articles')) {
            $name = $this->articleNames[$this->id($params)] ?? null;

            return $name instanceof \Throwable ? throw $name : ($name ?? false);
        }

        return isset($this->items[$this->id($params)]) ? $this->items[$this->id($params)]['track'] : false;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|false
     */
    private function fetchAssociative(string $sql, array $params = []): array|false
    {
        $item = $this->items[$this->id($params)] ?? null;

        foreach ($item['missing'] ?? [] as $fragment) {
            if (str_contains($sql, $fragment)) {
                return false;
            }
        }

        return match (true) {
            null === $item                              => false,
            str_contains($sql, 'MIN(expiry_date)')      => ['min_expiry' => $item['minExpiry']],
            str_contains($sql, 'threshold_amount FROM') => ['total_on_hand_amount' => $item['onHand'], 'threshold_amount' => $item['threshold']],
            str_contains($sql, 'reserved_amount FROM')  => ['total_on_hand_amount' => $item['onHand'], 'reserved_amount' => $item['reserved']],
            default                                     => ['clinic_id' => Uuid::fromString(self::CLINIC)->toBinary(), 'article_id' => Uuid::fromString(self::ARTICLE)->toBinary(), 'total_on_hand_unit' => 'TABLET'],
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function id(array $params): string
    {
        $binary = $params['id'] ?? '';

        return \is_string($binary) ? Uuid::fromBinary($binary)->toRfc4122() : '';
    }

    /**
     * @return list<array{StockAlertType, StockAlertSeverity, ?string, ?string, string, ?\DateTimeImmutable}>
     */
    private function summary(): array
    {
        return array_map(
            static fn (StockAlertView $a): array => [$a->type, $a->severity, $a->currentLevelAmount, $a->currentLevelUnit, $a->articleName, $a->resolvedAt],
            $this->alerts,
        );
    }

    /**
     * Keeps every row, resolved ones included; the latest row of a type is the one re-evaluated.
     */
    private function alertRepository(): StockAlertReadRepositoryInterface
    {
        $test = $this;

        return new class($test) implements StockAlertReadRepositoryInterface {
            public function __construct(private StockAlertProjectorTest $test)
            {
            }

            public function findActive(ClinicId $clinicId, ?StockAlertSeverity $severity): array
            {
                return [];
            }

            public function findByStockItemAndType(StockItemId $stockItemId, StockAlertType $type): ?StockAlertView
            {
                $found = null;

                foreach ($this->test->rows() as $alert) {
                    if ($alert->type === $type && $alert->stockItemId->toString() === $stockItemId->toString()) {
                        $found = $alert;
                    }
                }

                return $found;
            }

            public function findById(string $alertId): ?StockAlertView
            {
                return null;
            }

            public function save(StockAlertView $alert): void
            {
                $this->test->store($alert);
            }

            public function resolve(string $alertId, \DateTimeImmutable $resolvedAt): void
            {
                foreach ($this->test->rows() as $alert) {
                    if ($alert->alertId === $alertId) {
                        $this->test->store(new StockAlertView(
                            $alert->alertId,
                            $alert->clinicId,
                            $alert->stockItemId,
                            $alert->articleId,
                            $alert->articleName,
                            $alert->type,
                            $alert->severity,
                            $alert->currentLevelAmount,
                            $alert->currentLevelUnit,
                            $alert->earliestExpiry,
                            $alert->detectedAt,
                            $resolvedAt,
                        ));
                    }
                }
            }
        };
    }

    private function jobs(): StockNoticeJobsInterface
    {
        $test = $this;

        return new readonly class($test) implements StockNoticeJobsInterface {
            public function __construct(private StockAlertProjectorTest $test)
            {
            }

            public function notifyCriticalStock(string $clinicId, string $alertId): void
            {
                $this->test->notice($clinicId, $alertId);
            }

            public function notifyExpiringLots(string $clinicId): void
            {
                throw new \LogicException('Not used.');
            }
        };
    }
}
