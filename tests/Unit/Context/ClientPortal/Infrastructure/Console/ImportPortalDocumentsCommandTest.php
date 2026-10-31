<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Console;

use App\Context\ClientPortal\Application\Command\PublishInvoice\PublishInvoice;
use App\Context\ClientPortal\Application\Command\PublishPrescription\PublishPrescription;
use App\Context\ClientPortal\Application\Port\PublishableSource;
use App\Context\ClientPortal\Infrastructure\Console\ImportPortalDocumentsCommand;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ImportPortalDocumentsCommandTest extends TestCase
{
    private W $world;
    private AnsweringCommandBus $bus;

    protected function setUp(): void
    {
        $this->world = new W();
        $this->bus   = new AnsweringCommandBus(static fn (object $command): null => null);
    }

    public function testEveryPublishableSourceIsOfferedBatchByBatchSince24MonthsAgo(): void
    {
        for ($i = 1; $i <= 201; ++$i) {
            $this->world->invoices->publishable[] = new PublishableSource($this->id('a', $i), 'clinic-' . ($i % 2));
        }
        for ($i = 1; $i <= 200; ++$i) {
            $this->world->prescriptions->publishable[] = new PublishableSource($this->id('b', $i), 'clinic-1');
        }

        [$code, $output] = $this->import(null);

        self::assertSame(Command::SUCCESS, $code);
        self::assertSame(
            ['2024-10-29:-:200', '2024-10-29:' . $this->id('a', 200) . ':200'],
            $this->world->invoices->pages,
        );
        self::assertSame(
            ['2024-10-29:-:200', '2024-10-29:' . $this->id('b', 200) . ':200'],
            $this->world->prescriptions->pages,
            'a full last batch asks once more',
        );
        self::assertCount(401, $this->bus->commands);
        $first = $this->bus->commands[0];
        self::assertInstanceOf(PublishInvoice::class, $first);
        self::assertSame(['clinic-1', $this->id('a', 1)], [$first->clinicId, $first->invoiceId]);
        $last = $this->bus->commands[400];
        self::assertInstanceOf(PublishPrescription::class, $last);
        self::assertSame(['clinic-1', $this->id('b', 200)], [$last->clinicId, $last->prescriptionId]);
        self::assertStringContainsString(
            '201 invoice(s) and 200 prescription(s) offered to the Portal since 2024-10-29.',
            $output,
        );
    }

    public function testARecentSinceNarrowsTheImport(): void
    {
        [, $output] = $this->import('2026-01-15');

        self::assertSame(['2026-01-15:-:200'], $this->world->invoices->pages);
        self::assertSame(['2026-01-15:-:200'], $this->world->prescriptions->pages);
        self::assertStringContainsString(
            '0 invoice(s) and 0 prescription(s) offered to the Portal since 2026-01-15.',
            $output,
        );
    }

    public function testAnOlderSinceIsCappedAt24Months(): void
    {
        $this->import('2020-01-01');

        self::assertSame(['2024-10-29:-:200'], $this->world->invoices->pages);
    }

    public function testASinceThatIsNotADateIsRefused(): void
    {
        foreach (['yesterday', '2026-02-30', '31/10/2026'] as $since) {
            [$code, $output] = $this->import($since);

            self::assertSame(Command::INVALID, $code, $since);
            self::assertStringContainsString('--since expects a date as Y-m-d.', $output);
        }

        self::assertSame([], $this->world->invoices->pages);
        self::assertSame([], $this->bus->commands);
    }

    /**
     * @return array{int, string}
     */
    private function import(?string $since): array
    {
        $output  = new BufferedOutput();
        $command = new ImportPortalDocumentsCommand(
            $this->world->invoices,
            $this->world->prescriptions,
            $this->bus,
            $this->world->clock,
        );

        $code = $command(new SymfonyStyle(new ArrayInput([]), $output), $since);

        return [$code, preg_replace('/\s+/', ' ', $output->fetch()) ?? ''];
    }

    private function id(string $prefix, int $n): string
    {
        return \sprintf('01960000-0000-7000-8000-%s%011d', $prefix, $n);
    }
}
