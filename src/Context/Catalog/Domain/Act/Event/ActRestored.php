<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ActRestored extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'catalog';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $actId,
        public string $clinicId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->actId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'actId'    => $this->actId,
            'clinicId' => $this->clinicId,
        ];
    }
}
