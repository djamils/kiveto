<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/** Emitted when a new historical exchange rate is imported (source: ECB, manual or fixed). */
final readonly class ExchangeRateImported extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'money';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $id,
        private string $currencyFrom,
        private string $currencyTo,
        private string $rate,
        private string $effectiveDate,
        private string $source,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->id;
    }

    public function payload(): array
    {
        return [
            'id'            => $this->id,
            'currencyFrom'  => $this->currencyFrom,
            'currencyTo'    => $this->currencyTo,
            'rate'          => $this->rate,
            'effectiveDate' => $this->effectiveDate,
            'source'        => $this->source,
        ];
    }
}
