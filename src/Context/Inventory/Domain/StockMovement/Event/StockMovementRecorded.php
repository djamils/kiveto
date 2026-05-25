<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockMovement\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class StockMovementRecorded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'inventory';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $movementId,
        public string $clinicId,
        public string $articleId,
        public ?string $lotId,
        public string $type,
        public string $reason,
        public string $quantityAmount,
        public string $quantityUnit,
        public string $occurredAt,
        public ?string $reference,
        public ?string $performedBy,
        public ?string $note,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->movementId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'movementId'     => $this->movementId,
            'clinicId'       => $this->clinicId,
            'articleId'      => $this->articleId,
            'lotId'          => $this->lotId,
            'type'           => $this->type,
            'reason'         => $this->reason,
            'quantityAmount' => $this->quantityAmount,
            'quantityUnit'   => $this->quantityUnit,
            'occurredAt'     => $this->occurredAt,
            'reference'      => $this->reference,
            'performedBy'    => $this->performedBy,
            'note'           => $this->note,
        ];
    }
}
