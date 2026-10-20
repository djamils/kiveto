<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Console;

use App\System\ElectronicInvoicing\Application\Command\OpenPlatformConnection\OpenPlatformConnection;
use App\System\ElectronicInvoicing\Application\Command\VerifyPlatformConnection\VerifyPlatformConnection;
use App\System\ElectronicInvoicing\Domain\Exception\PlatformConnectionRefusedException;
use App\System\ElectronicInvoicing\Infrastructure\Console\ConnectPlatformCommand;
use App\Tests\Unit\System\ElectronicInvoicing\Support\CapturingBuses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConnectPlatformCommandTest extends TestCase
{
    public function testWithTheSimulatorTheConnectionIsVerifiedAtOnce(): void
    {
        $buses  = new CapturingBuses();
        $tester = new CommandTester(new ConnectPlatformCommand($buses));

        self::assertSame(Command::SUCCESS, $tester->execute(['siren' => '812456903', '--simulator' => true]));
        self::assertInstanceOf(OpenPlatformConnection::class, $buses->commands[0]);
        self::assertInstanceOf(VerifyPlatformConnection::class, $buses->commands[1]);
        self::assertStringContainsString('connected and verified', $tester->getDisplay());
    }

    public function testWithoutTheOptionTheConnectionWaitsForOnboarding(): void
    {
        $buses  = new CapturingBuses();
        $tester = new CommandTester(new ConnectPlatformCommand($buses));

        self::assertSame(Command::SUCCESS, $tester->execute(['siren' => '812456903']));
        self::assertCount(1, $buses->commands);
    }

    public function testARefusalIsReported(): void
    {
        $buses             = new CapturingBuses();
        $buses->onDispatch = static fn (): never => throw new PlatformConnectionRefusedException(PlatformConnectionRefusedException::NO_PLATFORM, 'Aucune plateforme de facturation électronique n\'est configurée.');
        $tester            = new CommandTester(new ConnectPlatformCommand($buses));

        self::assertSame(Command::FAILURE, $tester->execute(['siren' => '812456903']));
        self::assertStringContainsString('Aucune plateforme', $tester->getDisplay());
    }
}
