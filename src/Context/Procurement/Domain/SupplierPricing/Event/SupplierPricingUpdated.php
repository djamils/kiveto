<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierPricing\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class SupplierPricingUpdated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'procurement';
    protected const int VERSION            = 1;

    public function __construct(
        private string $pricingId,
        private string $clinicId,
        private string $catalogEntryId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->pricingId;
    }

    public function payload(): array
    {
        return [
            'pricingId'      => $this->pricingId,
            'clinicId'       => $this->clinicId,
            'catalogEntryId' => $this->catalogEntryId,
        ];
    }
}
