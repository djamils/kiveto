<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/** Emitted when a new currency is registered in the reference catalogue. */
final readonly class CurrencyRegistered extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'money';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $code,
        private string $symbol,
        private int $decimals,
        private string $displayName,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->code;
    }

    public function payload(): array
    {
        return [
            'code'        => $this->code,
            'symbol'      => $this->symbol,
            'decimals'    => $this->decimals,
            'displayName' => $this->displayName,
        ];
    }
}
