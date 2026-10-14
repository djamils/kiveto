<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

/**
 * What the stock page shows for one article: the figures, and enough of the lot
 * situation to colour the row.
 *
 * Quantities are decimal strings all the way through — the domain keeps them
 * exact and the page has no reason to turn them into floats.
 */
final readonly class StockSnapshotRow
{
    public function __construct(
        public string $stockItemId,
        public string $articleId,
        public string $onHand,
        public string $reserved,
        public string $available,
        public string $threshold,
        public string $unit,
        public string $thresholdType,
        public bool $trackStock,
        public string $status,
        public int $activeLotCount,
        public ?string $earliestExpiry,
        public int $expiredLotCount,
        public int $recalledLotCount,
        public ?string $location = null,
    ) {
    }

    public function isArchived(): bool
    {
        return 'ARCHIVED' === $this->status;
    }

    public function isOutOfStock(): bool
    {
        return $this->trackStock && 0 >= $this->compare($this->available, '0');
    }

    /**
     * Below the threshold without being out of it — the moment to reorder rather
     * than the moment it is too late.
     */
    public function isLow(): bool
    {
        return $this->trackStock
            && !$this->isOutOfStock()
            && 0 < $this->compare($this->threshold, '0')
            && 0 >= $this->compare($this->available, $this->threshold);
    }

    public function expiresWithin(int $days, \DateTimeImmutable $today): bool
    {
        if (null === $this->earliestExpiry) {
            return false;
        }

        $expiry = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->earliestExpiry);

        if (false === $expiry) {
            return false;
        }

        $limit = $today->modify(\sprintf('+%d days', $days));

        return $expiry <= $limit;
    }

    public function hasExpiredLots(): bool
    {
        return $this->expiredLotCount > 0;
    }

    public function hasRecalledLots(): bool
    {
        return $this->recalledLotCount > 0;
    }

    /**
     * The quantities are decimal strings kept exact by the domain; comparing
     * them as floats is what would lose a hundredth of a millilitre.
     */
    private function compare(string $left, string $right): int
    {
        /** @var numeric-string $a */
        $a = $left;
        /** @var numeric-string $b */
        $b = $right;

        return bccomp($a, $b, 6);
    }
}
