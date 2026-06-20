<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Infrastructure\Console\DemoApplyStarterCatalogCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoApplyStarterCatalogCommandTest extends TestCase
{
    public function testFailsWhenClinicOptionMissing(): void
    {
        $bus    = $this->createStub(CommandBusInterface::class);
        $tester = new CommandTester(new DemoApplyStarterCatalogCommand($bus));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('--clinic option is required', $tester->getDisplay());
    }

    public function testDispatchesApplyStarterCatalog(): void
    {
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoApplyStarterCatalogCommand($bus));
        $tester->execute(['--clinic' => 'clinic-uuid']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Starter catalog "companion" applied', $tester->getDisplay());
    }

    public function testFallsBackToDefaultTypeWhenInvalid(): void
    {
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoApplyStarterCatalogCommand($bus));
        $tester->execute(['--clinic' => 'clinic-uuid', '--type' => '']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('companion', $tester->getDisplay());
    }

    public function testReturnsFailureWhenDispatchThrows(): void
    {
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('catalog error'));

        $tester = new CommandTester(new DemoApplyStarterCatalogCommand($bus));
        $tester->execute(['--clinic' => 'clinic-uuid']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('catalog error', $tester->getDisplay());
    }
}
