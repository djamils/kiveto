<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Support;

use App\Context\Inventory\Application\Port\ClinicStaffInterface;
use App\Context\Inventory\Application\Port\StockAlertReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockAlert\ReadModel\StockAlertView;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertType;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;

/**
 * Clinique Montmartre's stock: ids and test doubles of the bell notices.
 */
final class StockNotices
{
    public const string CLINIC    = '01960000-0000-7000-8000-0000000c0001';
    public const string OTHER     = '01960000-0000-7000-8000-0000000c0002';
    public const string ALERT     = '01960000-0000-7000-8000-0000000a1001';
    public const string ITEM      = '01960000-0000-7000-8000-0000000a2001';
    public const string ARTICLE   = '01960000-0000-7000-8000-0000000a3001';
    public const string ASSISTANT = '01960000-0000-7000-8000-0000000d0003';
    public const string ADMIN     = '01960000-0000-7000-8000-0000000d0004';

    public static function alert(
        StockAlertType $type = StockAlertType::OUT_OF_STOCK,
        StockAlertSeverity $severity = StockAlertSeverity::CRITICAL,
        string $articleName = 'Vermifuge Milbemax',
        ?\DateTimeImmutable $resolvedAt = null,
    ): StockAlertView {
        return new StockAlertView(
            alertId: self::ALERT,
            clinicId: ClinicId::fromString(self::CLINIC),
            stockItemId: StockItemId::fromString(self::ITEM),
            articleId: ArticleId::fromString(self::ARTICLE),
            articleName: $articleName,
            type: $type,
            severity: $severity,
            currentLevelAmount: '0.000000',
            currentLevelUnit: 'TABLET',
            earliestExpiry: null,
            detectedAt: new \DateTimeImmutable('2026-10-26 09:00'),
            resolvedAt: $resolvedAt,
        );
    }

    public static function alerts(?StockAlertView $alert): StockAlertReadRepositoryInterface
    {
        return new class($alert) implements StockAlertReadRepositoryInterface {
            public function __construct(private ?StockAlertView $alert)
            {
            }

            public function findActive(ClinicId $clinicId, ?StockAlertSeverity $severity): array
            {
                throw new \LogicException('Not used.');
            }

            public function findByStockItemAndType(StockItemId $stockItemId, StockAlertType $type): ?StockAlertView
            {
                throw new \LogicException('Not used.');
            }

            public function findById(string $alertId): ?StockAlertView
            {
                return $this->alert?->alertId === $alertId ? $this->alert : null;
            }

            public function save(StockAlertView $alert): void
            {
                throw new \LogicException('Not used.');
            }

            public function resolve(string $alertId, \DateTimeImmutable $resolvedAt): void
            {
                throw new \LogicException('Not used.');
            }
        };
    }

    /**
     * @param array<string, list<string>> $assistants     clinic id => user ids
     * @param array<string, list<string>> $administrators clinic id => user ids
     */
    public static function staff(array $assistants = [self::CLINIC => [self::ASSISTANT]], array $administrators = [self::CLINIC => [self::ADMIN]]): ClinicStaffInterface
    {
        return new readonly class($assistants, $administrators) implements ClinicStaffInterface {
            /**
             * @param array<string, list<string>> $assistants
             * @param array<string, list<string>> $administrators
             */
            public function __construct(private array $assistants, private array $administrators)
            {
            }

            public function assistants(string $clinicId): array
            {
                return $this->assistants[$clinicId] ?? [];
            }

            public function administrators(string $clinicId): array
            {
                return $this->administrators[$clinicId] ?? [];
            }
        };
    }
}
