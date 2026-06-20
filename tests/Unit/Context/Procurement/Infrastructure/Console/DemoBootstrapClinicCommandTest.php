<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Infrastructure\Console\DemoBootstrapClinicCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoBootstrapClinicCommandTest extends TestCase
{
    public function testBootstrapsClinicWithDefaults(): void
    {
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoBootstrapClinicCommand($bus));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Clinique Demo', $tester->getDisplay());
        self::assertStringContainsString('clinicId', $tester->getDisplay());
    }

    public function testFallsBackToDefaultsWhenOptionsBlank(): void
    {
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoBootstrapClinicCommand($bus));
        $tester->execute(['--name' => '', '--country' => '', '--currency' => '']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testAcceptsCustomOptions(): void
    {
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoBootstrapClinicCommand($bus));
        $tester->execute(['--name' => 'Clinique XYZ', '--country' => 'us', '--currency' => 'usd']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Clinique XYZ', $tester->getDisplay());
    }

    public function testReturnsFailureWhenDispatchThrows(): void
    {
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('clinic error'));

        $tester = new CommandTester(new DemoBootstrapClinicCommand($bus));
        $tester->execute(['--name' => 'Clinique XYZ']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('clinic error', $tester->getDisplay());
    }
}
