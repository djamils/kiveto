<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event\Fixture;

use App\Shared\Domain\Event\AbstractDomainEvent;

final class InvoiceItemAdded extends AbstractDomainEvent
{
    protected const BOUNDED_CONTEXT = 'test-bc';
    protected const VERSION         = 1;

    public function aggregateId(): string
    {
        return 'invoice-789';
    }

    public function payload(): array
    {
        return [
            'itemId' => 'item-001',
            'amount' => 50.00,
        ];
    }
}
