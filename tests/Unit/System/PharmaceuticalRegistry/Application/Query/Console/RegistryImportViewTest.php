<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\PharmaceuticalRegistry\Application\Query\Console;

use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RegistryImportViewTest extends TestCase
{
    public function testTheChangesAreTheEntriesAddedChangedAndWithdrawn(): void
    {
        self::assertSame(15, self::import('APPLIED', created: 5, updated: 8, withdrawn: 2)->changes());
        self::assertSame(0, self::import('FAILED')->changes());
    }

    #[DataProvider('provideTheStatusTellsWhereTheImportStandsCases')]
    public function testTheStatusTellsWhereTheImportStands(string $status, bool $running, bool $failed, bool $applied): void
    {
        $import = self::import($status);

        self::assertSame([$running, $failed, $applied], [$import->isRunning(), $import->isFailed(), $import->isApplied()]);
    }

    /**
     * @return iterable<string, array{string, bool, bool, bool}>
     */
    public static function provideTheStatusTellsWhereTheImportStandsCases(): iterable
    {
        yield 'pending' => ['PENDING', true, false, false];

        yield 'running' => ['RUNNING', true, false, false];

        yield 'diffed' => ['DIFFED', true, false, false];

        yield 'applying' => ['APPLYING', true, false, false];

        yield 'applied' => ['APPLIED', false, false, true];

        yield 'failed' => ['FAILED', false, true, false];
    }

    public function testTheDurationRunsFromTheDownloadToTheEnd(): void
    {
        self::assertSame(52, self::import('APPLIED', appliedAt: '2026-10-01 08:00:52')->seconds());
        self::assertSame(0, self::import('APPLIED', appliedAt: '2026-10-01 07:59:00')->seconds(), 'never negative');
        self::assertNull(self::import('RUNNING')->seconds(), 'not ended');
    }

    private static function import(string $status, ?string $appliedAt = null, int $created = 0, int $updated = 0, int $withdrawn = 0): RegistryImportView
    {
        return new RegistryImportView(
            '01960000-0000-7000-8000-0000000a1001',
            $status,
            new \DateTimeImmutable('2026-10-01 08:00:00', new \DateTimeZone('UTC')),
            null !== $appliedAt ? new \DateTimeImmutable($appliedAt, new \DateTimeZone('UTC')) : null,
            null,
            $created,
            $updated,
            $withdrawn,
        );
    }
}
