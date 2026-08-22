<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

/**
 * One line of the movement trail, as the page lists it.
 */
final readonly class MovementRow
{
    public function __construct(
        public string $id,
        public string $articleId,
        public ?string $lotId,
        public ?string $lotNumber,
        public string $type,
        public string $reason,
        public string $quantity,
        public string $unit,
        public \DateTimeImmutable $occurredAt,
        public ?string $reference,
        public ?string $performedBy,
        public ?string $note,
    ) {
    }

    /**
     * A receipt adds and a consumption removes, both recorded as a positive
     * quantity. Only an adjustment carries its own sign.
     */
    public function isIncoming(): bool
    {
        if ('IN' === $this->type) {
            return true;
        }

        if ('OUT' === $this->type) {
            return false;
        }

        /** @var numeric-string $quantity */
        $quantity = $this->quantity;

        return 0 <= bccomp($quantity, '0', 6);
    }
}
