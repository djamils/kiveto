<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Command;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\StockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotNumber;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThreshold;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThresholdType;
use App\Context\Inventory\Domain\StockMovement\Repository\StockMovementRepositoryInterface;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Tests\Shared\Time\FrozenClock;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * What the Inventory command handlers are built from in a unit test: a stock
 * item held in memory with the lots a test asks for, the movements it writes
 * kept for inspection, a transaction that simply runs, and an event bus that
 * records what was announced.
 */
abstract class InventoryCommandFixtures extends TestCase
{
    public const string CLINIC_ID     = '01970000-0000-7000-8000-000000000001';
    public const string ARTICLE_ID    = '01970000-0000-7000-8000-000000000002';
    public const string STOCK_ITEM_ID = '01970000-0000-7000-8000-000000000003';
    public const string LOT_A         = '01970000-0000-7000-8000-00000000000a';
    public const string LOT_B         = '01970000-0000-7000-8000-00000000000b';
    public const string USER_ID       = '01970000-0000-7000-8000-000000000009';

    protected StockItemRepositoryInterface $items;

    protected StockMovementRepositoryInterface $movements;

    /** @var list<StockMovement> */
    protected array $written = [];

    /** @var list<object> */
    protected array $announced = [];

    /** @var array<string, StockItem> */
    private array $held = [];

    protected function setUp(): void
    {
        $held = &$this->held;

        $this->items = new class($held) implements StockItemRepositoryInterface {
            /** @param array<string, StockItem> $held */
            public function __construct(private array &$held)
            {
            }

            public function save(StockItem $stockItem): void
            {
                $this->held[$stockItem->id()->toString()] = $stockItem;
            }

            public function findById(StockItemId $id, ClinicId $clinicId): ?StockItem
            {
                $item = $this->held[$id->toString()] ?? null;

                return null !== $item && $item->clinicId()->equals($clinicId) ? $item : null;
            }

            public function findByClinicAndArticle(ClinicId $clinicId, ArticleId $articleId): ?StockItem
            {
                foreach ($this->held as $item) {
                    if ($item->clinicId()->equals($clinicId) && $item->articleId()->equals($articleId)) {
                        return $item;
                    }
                }

                return null;
            }
        };

        $written = &$this->written;

        $this->movements = new class($written) implements StockMovementRepositoryInterface {
            /** @param list<StockMovement> $written */
            public function __construct(private array &$written)
            {
            }

            public function save(StockMovement $movement): void
            {
                $this->written[] = $movement;
            }

            public function findById(\App\Context\Inventory\Domain\StockMovement\ValueObject\StockMovementId $id): ?StockMovement
            {
                return null;
            }

            public function findByClinic(
                ClinicId $clinicId,
                ?\DateTimeImmutable $from,
                ?\DateTimeImmutable $to,
                ?\App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType $type,
                ?\App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason $reason,
                int $limit,
                int $offset,
            ): array {
                return $this->written;
            }
        };
    }

    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-21 10:42:00');
    }

    protected function clock(): FrozenClock
    {
        return new FrozenClock(self::now());
    }

    protected function publisher(): DomainEventPublisher
    {
        $announced = &$this->announced;
        $bus       = $this->createStub(EventBusInterface::class);
        $bus->method('publish')->willReturnCallback(static function (array $stamps, object ...$events) use (&$announced): void {
            array_push($announced, ...$events);
        });

        return new DomainEventPublisher($bus);
    }

    protected function entityManager(): EntityManagerInterface
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $work): mixed => $work());

        return $em;
    }

    /**
     * A stock item of tablets, held in memory, with the lots asked for:
     * [lot id, number, quantity, expiry].
     *
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $lots
     */
    protected function stockItem(array $lots = [], bool $trackStock = true): StockItem
    {
        $unit = UnitOfMeasure::fromString('TABLET');
        $item = StockItem::open(
            id: StockItemId::fromString(self::STOCK_ITEM_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            unit: $unit,
            threshold: new StockThreshold(Quantity::of('10.000000', $unit), StockThresholdType::ABSOLUTE),
            trackStock: $trackStock,
            clock: new FrozenClock(self::now()->modify('-60 days')),
        );

        foreach ($lots as [$id, $number, $quantity, $expiry]) {
            $item->receiveStock(
                lotNumber: LotNumber::fromString($number),
                quantity: Quantity::of($quantity, $unit),
                expiryDate: new \DateTimeImmutable($expiry),
                sourceSupplier: null,
                occurredAt: self::now()->modify('-30 days'),
                updatedAt: self::now()->modify('-30 days'),
                newLotId: LotId::fromString($id),
            );
        }

        $_ = $item->pullDomainEvents();
        $this->items->save($item);

        return $item;
    }

    /**
     * @param class-string $class
     *
     * @return list<object>
     */
    protected function announcedOf(string $class): array
    {
        return array_values(array_filter($this->announced, static fn (object $event): bool => $event instanceof $class));
    }
}
