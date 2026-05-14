<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/** Emitted when an active currency is deactivated. */
final readonly class CurrencyDeactivated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'money';
    protected const int    VERSION         = 1;

    public function __construct(private string $code)
    {
    }

    public function aggregateId(): string
    {
        return $this->code;
    }

    public function payload(): array
    {
        return ['code' => $this->code];
    }
}
