<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Application\Port\ExpiryNoticeRepositoryInterface;
use App\Context\Inventory\Application\Port\StockAlertReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockAlert\ReadModel\StockAlertView;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertType;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\ExpiryNotificationEntity;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockItemEntity;
use App\Fixtures\Context\Inventory\Factory\LotEntityFactory;
use App\Fixtures\Context\Inventory\Factory\StockItemEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The stock alert rows the bell notices are read from, and the record of
 * the lots already announced « Péremption proche ».
 */
final class StockNoticePersistenceTest extends KernelTestCase
{
    use Factories;

    private string $clinicId;

    protected function setUp(): void
    {
        $this->clinicId = Uuid::v7()->toRfc4122();
    }

    public function testAlertRowsAreWrittenReadAndResolved(): void
    {
        $alerts  = $this->alerts();
        $item    = $this->stockItem();
        $itemId  = StockItemId::fromString($item->getId()->toRfc4122());
        $alertId = Uuid::v7()->toRfc4122();

        self::assertNull($alerts->findById($alertId));
        self::assertNull($alerts->findByStockItemAndType($itemId, StockAlertType::OUT_OF_STOCK));

        $alerts->save($this->alert($alertId, $itemId, StockAlertType::OUT_OF_STOCK, StockAlertSeverity::CRITICAL, '0.000000', null));
        $alerts->save($this->alert(Uuid::v7()->toRfc4122(), $itemId, StockAlertType::LOW_EXPIRY, StockAlertSeverity::INFO, null, new \DateTimeImmutable('2026-11-20')));

        $found = $alerts->findById($alertId);
        self::assertNotNull($found);
        self::assertSame(['Vermifuge Milbemax', StockAlertType::OUT_OF_STOCK, StockAlertSeverity::CRITICAL, '0.000000', 'TABLET', null], [$found->articleName, $found->type, $found->severity, $found->currentLevelAmount, $found->currentLevelUnit, $found->resolvedAt]);
        self::assertSame($alertId, $alerts->findByStockItemAndType($itemId, StockAlertType::OUT_OF_STOCK)?->alertId);
        self::assertSame('2026-11-20', $alerts->findByStockItemAndType($itemId, StockAlertType::LOW_EXPIRY)?->earliestExpiry?->format('Y-m-d'));

        $alerts->save($this->alert($alertId, $itemId, StockAlertType::OUT_OF_STOCK, StockAlertSeverity::WARNING, '1.000000', null));
        self::assertSame(StockAlertSeverity::WARNING, $alerts->findById($alertId)?->severity);

        $clinic = ClinicId::fromString($this->clinicId);
        self::assertCount(2, $alerts->findActive($clinic, null));
        self::assertCount(1, $alerts->findActive($clinic, StockAlertSeverity::INFO));

        $alerts->resolve($alertId, new \DateTimeImmutable('2026-10-26 10:00:00'));
        self::assertNull($alerts->findByStockItemAndType($itemId, StockAlertType::OUT_OF_STOCK));
        self::assertSame('2026-10-26 10:00', $alerts->findById($alertId)?->resolvedAt?->format('Y-m-d H:i'));
    }

    public function testOnlyTheActiveLotsOfTrackedItemsInTheWindowAreAnnouncedOnce(): void
    {
        $notices = $this->notices();
        $item    = $this->stockItem();
        $soon    = $this->lot($item, '+3 days');
        $later   = $this->lot($item, '+29 days');
        $this->lot($item, '+45 days');
        $this->lot($item, '-1 day');
        $this->lot($item, '+5 days', 'RECALLED');
        $this->lot($this->stockItem(tracked: false), '+5 days');
        $this->lot($this->stockItem(archived: true), '+5 days');

        $otherClinic    = $this->clinicId;
        $this->clinicId = Uuid::v7()->toRfc4122();
        $elsewhere      = $this->lot($this->stockItem(), '+10 days');

        $from  = new \DateTimeImmutable('today');
        $until = $from->modify('+30 days');

        self::assertSame([$soon, $later], $notices->unannouncedLotsExpiringBetween($otherClinic, $from, $until));
        self::assertSame([$elsewhere], $notices->unannouncedLotsExpiringBetween($this->clinicId, $from, $until));
        $clinics = $notices->clinicsWithUnannouncedLotsExpiringBetween($from, $until);
        self::assertContains($otherClinic, $clinics);
        self::assertContains($this->clinicId, $clinics);

        $notices->markAnnounced([$soon, $later], new \DateTimeImmutable('2026-10-27 07:13', new \DateTimeZone('Europe/Paris')));
        $notices->markAnnounced([$soon], new \DateTimeImmutable());

        self::assertSame([], $notices->unannouncedLotsExpiringBetween($otherClinic, $from, $until));
        self::assertNotContains($otherClinic, $notices->clinicsWithUnannouncedLotsExpiringBetween($from, $until));
        self::assertSame([$elsewhere], $notices->unannouncedLotsExpiringBetween($this->clinicId, $from, $until));

        $entity = new ExpiryNotificationEntity(Uuid::fromString($soon), new \DateTimeImmutable('2026-10-27 06:13'));
        self::assertSame($soon, $entity->columns()['lot_id']->toRfc4122());
        self::assertSame('2026-10-27 06:13', $entity->columns()['notified_at']->format('Y-m-d H:i'));
    }

    private function alerts(): StockAlertReadRepositoryInterface
    {
        $alerts = self::getContainer()->get(StockAlertReadRepositoryInterface::class);
        \assert($alerts instanceof StockAlertReadRepositoryInterface);

        return $alerts;
    }

    private function notices(): ExpiryNoticeRepositoryInterface
    {
        $notices = self::getContainer()->get(ExpiryNoticeRepositoryInterface::class);
        \assert($notices instanceof ExpiryNoticeRepositoryInterface);

        return $notices;
    }

    private function alert(string $id, StockItemId $itemId, StockAlertType $type, StockAlertSeverity $severity, ?string $level, ?\DateTimeImmutable $expiry): StockAlertView
    {
        return new StockAlertView(
            alertId: $id,
            clinicId: ClinicId::fromString($this->clinicId),
            stockItemId: $itemId,
            articleId: ArticleId::fromString(Uuid::v7()->toRfc4122()),
            articleName: 'Vermifuge Milbemax',
            type: $type,
            severity: $severity,
            currentLevelAmount: $level,
            currentLevelUnit: null !== $level ? 'TABLET' : null,
            earliestExpiry: $expiry,
            detectedAt: new \DateTimeImmutable('2026-10-26 09:00:00'),
            resolvedAt: null,
        );
    }

    private function stockItem(bool $tracked = true, bool $archived = false): StockItemEntity
    {
        $factory = StockItemEntityFactory::new()->withClinicId($this->clinicId)->withArticleId(Uuid::v7()->toRfc4122());
        $factory = $tracked ? $factory : $factory->noTracking();
        $factory = $archived ? $factory->archived() : $factory;

        return $factory->create()->_real();
    }

    private function lot(StockItemEntity $stockItem, string $expiry, string $status = 'ACTIVE'): string
    {
        $id = Uuid::v7();
        LotEntityFactory::new()->create(['id' => $id, 'stockItem' => $stockItem, 'expiryDate' => new \DateTimeImmutable($expiry), 'status' => $status]);

        return $id->toRfc4122();
    }
}
