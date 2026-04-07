<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Command;

use App\Animal\Application\Command\UpdateAnimalLifeCycle\UpdateAnimalLifeCycle;
use App\Shared\Application\Bus\CommandInterface;
use PHPUnit\Framework\TestCase;

final class UpdateAnimalLifeCycleTest extends TestCase
{
    public function testConstruct(): void
    {
        $command = new UpdateAnimalLifeCycle(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef',
            lifeStatus: 'deceased',
            deceasedAt: '2024-06-01',
            missingSince: null,
        );

        self::assertInstanceOf(CommandInterface::class, $command);
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $command->clinicId);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $command->animalId);
        self::assertSame('deceased', $command->lifeStatus);
        self::assertSame('2024-06-01', $command->deceasedAt);
        self::assertNull($command->missingSince);
    }
}
