<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PriceListCreated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'catalog';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $priceListId,
        public string $clinicId,
        public string $name,
        public bool $isDefault,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->priceListId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'priceListId' => $this->priceListId,
            'clinicId'    => $this->clinicId,
            'name'        => $this->name,
            'isDefault'   => $this->isDefault,
        ];
    }
}
