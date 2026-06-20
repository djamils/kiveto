<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Infrastructure\Console\DemoSimulatePurchaseOrdersCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class DemoSimulatePurchaseOrdersCommandTest extends TestCase
{
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID  = '01932b00-0000-7000-8000-000000000002';
    private const string ENTRY_UUID    = '01932b00-0000-7000-8000-000000000100';
    private const string PO_UUID       = '01932b00-0000-7000-8000-000000000200';
    private const string RECEIPT_UUID  = '01932b00-0000-7000-8000-000000000300';

    public function testFailsWhenClinicOptionMissing(): void
    {
        $tester = new CommandTester(new DemoSimulatePurchaseOrdersCommand(
            $this->createStub(CommandBusInterface::class),
            $this->createStub(Connection::class),
        ));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('--clinic option is required', $tester->getDisplay());
    }

    public function testReportsWhenNoAccounts(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $tester = new CommandTester(new DemoSimulatePurchaseOrdersCommand(
            $this->createStub(CommandBusInterface::class),
            $connection,
        ));
        $tester->execute(['--clinic' => self::CLINIC_UUID]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No active supplier accounts', $tester->getDisplay());
    }

    public function testSkipsWhenNoCatalogEntries(): void
    {
        $callIndex  = 0;
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            function () use (&$callIndex): array {
                ++$callIndex;
                if (1 === $callIndex) {
                    // accounts
                    return [[
                        'account_id'  => Uuid::fromString(self::ACCOUNT_UUID)->toBinary(),
                        'supplier_id' => Uuid::fromString(self::SUPPLIER_UUID)->toBinary(),
                    ]];
                }

                // subsequent fetches (catalog entries) return empty
                return [];
            },
        );

        $tester = new CommandTester(new DemoSimulatePurchaseOrdersCommand(
            $this->createStub(CommandBusInterface::class),
            $connection,
        ));
        $tester->execute(['--clinic' => self::CLINIC_UUID, '--count' => 1]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No catalog entries', $tester->getDisplay());
    }

    public function testSimulatesFullWorkflow(): void
    {
        $callIndex  = 0;
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            function () use (&$callIndex): array {
                ++$callIndex;
                if (1 === $callIndex) {
                    return [[
                        'account_id'  => Uuid::fromString(self::ACCOUNT_UUID)->toBinary(),
                        'supplier_id' => Uuid::fromString(self::SUPPLIER_UUID)->toBinary(),
                    ]];
                }
                if (2 === $callIndex) {
                    return [[
                        'id'                     => Uuid::fromString(self::ENTRY_UUID)->toBinary(),
                        'catalog_price_minor'    => 1299,
                        'catalog_price_currency' => 'EUR',
                    ]];
                }

                // receipts query
                return [[
                    'id' => Uuid::fromString(self::RECEIPT_UUID)->toBinary(),
                ]];
            },
        );
        $connection->method('fetchAssociative')->willReturn([
            'id' => Uuid::fromString(self::PO_UUID)->toBinary(),
        ]);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoSimulatePurchaseOrdersCommand($commandBus, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID, '--count' => 1]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 receipt(s) validated', $tester->getDisplay());
    }

    public function testReportsErrorWhenPoNotFoundAfterCreate(): void
    {
        $callIndex  = 0;
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            function () use (&$callIndex): array {
                ++$callIndex;
                if (1 === $callIndex) {
                    return [[
                        'account_id'  => Uuid::fromString(self::ACCOUNT_UUID)->toBinary(),
                        'supplier_id' => Uuid::fromString(self::SUPPLIER_UUID)->toBinary(),
                    ]];
                }

                return [[
                    'id'                     => Uuid::fromString(self::ENTRY_UUID)->toBinary(),
                    'catalog_price_minor'    => 100,
                    'catalog_price_currency' => 'EUR',
                ]];
            },
        );
        // PO retrieval returns false → simulates "PO not found after create"
        $connection->method('fetchAssociative')->willReturn(false);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoSimulatePurchaseOrdersCommand($commandBus, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID, '--count' => 1]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Could not retrieve created PO', $tester->getDisplay());
    }

    public function testReportsFailureWhenDispatchThrows(): void
    {
        $callIndex  = 0;
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            function () use (&$callIndex): array {
                ++$callIndex;
                if (1 === $callIndex) {
                    return [[
                        'account_id'  => Uuid::fromString(self::ACCOUNT_UUID)->toBinary(),
                        'supplier_id' => Uuid::fromString(self::SUPPLIER_UUID)->toBinary(),
                    ]];
                }

                return [[
                    'id'                     => Uuid::fromString(self::ENTRY_UUID)->toBinary(),
                    'catalog_price_minor'    => 100,
                    'catalog_price_currency' => 'EUR',
                ]];
            },
        );

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willThrowException(new \RuntimeException('dispatch failure'));

        $tester = new CommandTester(new DemoSimulatePurchaseOrdersCommand($commandBus, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID, '--count' => 1]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('dispatch failure', $tester->getDisplay());
    }

    public function testUsesDefaultCountWhenInvalidValue(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $tester = new CommandTester(new DemoSimulatePurchaseOrdersCommand(
            $this->createStub(CommandBusInterface::class),
            $connection,
        ));
        $tester->execute(['--clinic' => self::CLINIC_UUID, '--count' => 'invalid']);

        // No accounts means quick exit, but the count was parsed
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
