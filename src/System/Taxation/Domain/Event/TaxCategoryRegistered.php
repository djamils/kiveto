<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class TaxCategoryRegistered extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'taxation';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $code,
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
            'displayName' => $this->displayName,
        ];
    }
}
