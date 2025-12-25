<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Aggregate\Fixture;

use App\Shared\Domain\Aggregate\AggregateRoot;

final class TestAggregate extends AggregateRoot
{
    public function doSomething(): void
    {
        $this->recordDomainEvent(new TestEvent('event-1', 'aggregate-1'));
    }

    public function doSomethingElse(): void
    {
        $this->recordDomainEvent(new TestEvent('event-2', 'aggregate-1'));
    }
}
