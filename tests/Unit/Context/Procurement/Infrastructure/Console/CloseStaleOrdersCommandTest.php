<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Infrastructure\Console\CloseStaleOrdersCommand;
use App\Shared\Application\Bus\QueryBusInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CloseStaleOrdersCommandTest extends TestCase
{
    public function testItReportsNoStaleOrdersWhenEmpty(): void
    {
        $bus = $this->createStub(QueryBusInterface::class);
        $bus->method('ask')->willReturn([]);

        $tester = new CommandTester(new CloseStaleOrdersCommand($bus));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No stale orders found', $tester->getDisplay());
    }

    public function testItPrintsStaleOrdersTable(): void
    {
        $bus = $this->createStub(QueryBusInterface::class);
        $bus->method('ask')->willReturn([
            [
                'id'          => 'po-id-1',
                'orderNumber' => 'PO-2026-0001',
                'clinicId'    => 'clinic-id-1',
                'submittedAt' => '2025-01-01 10:00:00',
            ],
        ]);

        $tester = new CommandTester(new CloseStaleOrdersCommand($bus));
        $tester->execute(['--days' => 30]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('PO-2026-0001', $tester->getDisplay());
        self::assertStringContainsString('stale order(s) detected', $tester->getDisplay());
    }

    public function testItUsesDefaultDaysWhenInvalidValueGiven(): void
    {
        $bus = $this->createStub(QueryBusInterface::class);
        $bus->method('ask')->willReturn([]);

        $tester = new CommandTester(new CloseStaleOrdersCommand($bus));
        $tester->execute(['--days' => 'invalid']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('> 60 days', $tester->getDisplay());
    }

    public function testItGracefullyHandlesNonStringRowFields(): void
    {
        $bus = $this->createStub(QueryBusInterface::class);
        $bus->method('ask')->willReturn([
            [
                'id'          => 42, // non-string → empty
                'orderNumber' => 'PO-X',
                'clinicId'    => null,
                'submittedAt' => 'date-str',
            ],
        ]);

        $tester = new CommandTester(new CloseStaleOrdersCommand($bus));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }
}
