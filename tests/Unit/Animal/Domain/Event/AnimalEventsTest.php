<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Domain\Event;

use App\Animal\Domain\Event\AnimalArchived;
use App\Animal\Domain\Event\AnimalCreated;
use PHPUnit\Framework\TestCase;

final class AnimalEventsTest extends TestCase
{
    public function testAnimalCreated(): void
    {
        $event = new AnimalCreated(
            animalId: 'animal-123',
            clinicId: 'clinic-456',
            name: 'Rex',
            primaryOwnerClientId: 'client-789'
        );

        self::assertSame('animal-123', $event->aggregateId());
        self::assertSame('animal.animal.created.v1', $event->name());

        $payload = $event->payload();
        self::assertSame('animal-123', $payload['animalId']);
        self::assertSame('clinic-456', $payload['clinicId']);
        self::assertSame('Rex', $payload['name']);
        self::assertSame('client-789', $payload['primaryOwnerClientId']);
    }

    public function testAnimalArchived(): void
    {
        $event = new AnimalArchived(
            animalId: 'animal-456',
            clinicId: 'clinic-789'
        );

        self::assertSame('animal-456', $event->aggregateId());
        self::assertSame('animal.animal.archived.v1', $event->name());

        $payload = $event->payload();
        self::assertSame('animal-456', $payload['animalId']);
        self::assertSame('clinic-789', $payload['clinicId']);
    }
}
