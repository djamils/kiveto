<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Command;

use App\Animal\Application\Command\ArchiveAnimal\ArchiveAnimal;
use App\Shared\Application\Bus\CommandInterface;
use PHPUnit\Framework\TestCase;

final class ArchiveAnimalTest extends TestCase
{
    public function testConstruct(): void
    {
        $command = new ArchiveAnimal(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef',
        );

        self::assertInstanceOf(CommandInterface::class, $command);
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $command->clinicId);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $command->animalId);
    }
}
