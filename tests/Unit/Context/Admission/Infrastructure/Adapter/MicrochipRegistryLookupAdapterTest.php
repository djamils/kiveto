<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission\Infrastructure\Adapter;

use App\Context\Admission\Infrastructure\Adapter\Regulatory\MicrochipRegistryLookupAdapter;
use App\Context\Regulatory\Application\Command\OpenMicrochipRegistryLookup\OpenMicrochipRegistryLookup;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\TestCase;

final class MicrochipRegistryLookupAdapterTest extends TestCase
{
    public function testInitiateChipLookupDispatchesOpenMicrochipRegistryLookupCommand(): void
    {
        $commandBus = $this->createMock(CommandBusInterface::class);
        $commandBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (mixed $command): bool {
                return $command instanceof OpenMicrochipRegistryLookup
                    && '123456789012345' === $command->chipNumber
                    && '550e8400-e29b-41d4-a716-446655440000' === $command->clinicId;
            }))
        ;

        $adapter = new MicrochipRegistryLookupAdapter($commandBus);
        $adapter->initiateChipLookup('123456789012345', '550e8400-e29b-41d4-a716-446655440000');
    }
}
