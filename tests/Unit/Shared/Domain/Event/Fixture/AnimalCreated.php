<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event\Fixture;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class AnimalCreated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'test-bc';
    protected const int    VERSION         = 2;

    public function aggregateId(): string
    {
        return 'animal-456';
    }

    public function payload(): array
    {
        return [
            'name'    => 'Rex',
            'species' => 'Dog',
        ];
    }
}
