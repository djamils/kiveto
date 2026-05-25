<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class StockThresholdChanged extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'inventory';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $articleId,
        public string $thresholdAmount,
        public string $thresholdUnit,
        public string $thresholdType,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->stockItemId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'stockItemId'     => $this->stockItemId,
            'clinicId'        => $this->clinicId,
            'articleId'       => $this->articleId,
            'thresholdAmount' => $this->thresholdAmount,
            'thresholdUnit'   => $this->thresholdUnit,
            'thresholdType'   => $this->thresholdType,
        ];
    }
}
