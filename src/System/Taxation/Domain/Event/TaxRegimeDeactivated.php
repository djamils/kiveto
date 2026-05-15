<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class TaxRegimeDeactivated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'taxation';
    protected const int    VERSION         = 1;

    public function __construct(private string $regimeId)
    {
    }

    public function aggregateId(): string
    {
        return $this->regimeId;
    }

    public function payload(): array
    {
        return ['regimeId' => $this->regimeId];
    }
}
