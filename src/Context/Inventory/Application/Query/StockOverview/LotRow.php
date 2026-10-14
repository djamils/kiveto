<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

/**
 * One lot of a stock item, as the stock page shows it: where it came from,
 * and — for a recalled lot — why it was withdrawn.
 */
final readonly class LotRow
{
    public function __construct(
        public string $id,
        public string $stockItemId,
        public string $number,
        public string $quantity,
        public string $unit,
        public string $expiryDate,
        public string $status,
        public ?string $receivedAt = null,
        public ?string $reference = null,
        public ?string $recallReason = null,
        public ?string $changedAt = null,
    ) {
    }

    public function isActive(): bool
    {
        return 'ACTIVE' === $this->status;
    }

    public function expiresWithin(int $days, \DateTimeImmutable $today): bool
    {
        $expiry = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->expiryDate);

        return false !== $expiry && $expiry <= $today->modify(\sprintf('+%d days', $days));
    }

    public function isExpired(\DateTimeImmutable $today): bool
    {
        $expiry = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->expiryDate);

        return false !== $expiry && $expiry < $today;
    }
}
