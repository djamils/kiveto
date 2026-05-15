<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class TaxRateAdded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'taxation';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $regimeId,
        private string $rateId,
        private int $valueBasisPoints,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->regimeId;
    }

    public function payload(): array
    {
        return [
            'regimeId'         => $this->regimeId,
            'rateId'           => $this->rateId,
            'valueBasisPoints' => $this->valueBasisPoints,
        ];
    }
}
