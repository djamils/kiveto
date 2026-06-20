<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Infrastructure\Console\DemoSimulateConsumptionCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class DemoSimulateConsumptionCommandTest extends TestCase
{
    private const string CLINIC_UUID    = '01932b00-0000-7000-8000-000000000003';
    private const string STOCK_ITEM_BIN = '01932b00-0000-7000-8000-000000000050';
    private const string ARTICLE_UUID   = '01932b00-0000-7000-8000-000000000200';

    public function testFailsWhenClinicOptionMissing(): void
    {
        $tester = new CommandTester(new DemoSimulateConsumptionCommand(
            $this->createStub(CommandBusInterface::class),
            $this->createStub(Connection::class),
        ));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('--clinic option is required', $tester->getDisplay());
    }

    public function testSkipsWhenNoStockItems(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $tester = new CommandTester(new DemoSimulateConsumptionCommand(
            $this->createStub(CommandBusInterface::class),
            $connection,
        ));
        $tester->execute(['--clinic' => self::CLINIC_UUID]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No tracked stock items', $tester->getDisplay());
    }

    public function testSimulatesConsumption(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'id'                   => Uuid::fromString(self::STOCK_ITEM_BIN)->toBinary(),
                'article_id'           => Uuid::fromString(self::ARTICLE_UUID)->toBinary(),
                'total_on_hand_amount' => '100',
                'total_on_hand_unit'   => 'UNIT',
            ],
        ]);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoSimulateConsumptionCommand($commandBus, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID, '--days' => 2]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Simulated 2 day(s)', $tester->getDisplay());
    }

    public function testHandlesDispatchErrorsGracefully(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'id'                   => Uuid::fromString(self::STOCK_ITEM_BIN)->toBinary(),
                'article_id'           => Uuid::fromString(self::ARTICLE_UUID)->toBinary(),
                'total_on_hand_amount' => '100',
                'total_on_hand_unit'   => 'UNIT',
            ],
        ]);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willThrowException(new \RuntimeException('consume failure'));

        $tester = new CommandTester(new DemoSimulateConsumptionCommand($commandBus, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID, '--days' => 1]);

        // The command keeps SUCCESS even when individual dispatches fail
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testUsesDefaultDaysWhenInvalidValue(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'id'                   => Uuid::fromString(self::STOCK_ITEM_BIN)->toBinary(),
                'article_id'           => Uuid::fromString(self::ARTICLE_UUID)->toBinary(),
                'total_on_hand_amount' => '100',
                'total_on_hand_unit'   => 'UNIT',
            ],
        ]);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoSimulateConsumptionCommand($commandBus, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID, '--days' => 'invalid']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Simulated 14 day(s)', $tester->getDisplay());
    }
}
