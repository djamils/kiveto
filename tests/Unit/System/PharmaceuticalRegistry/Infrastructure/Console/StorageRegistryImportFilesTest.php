<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\System\PharmaceuticalRegistry\Infrastructure\Console\ImportFileManager;
use App\System\PharmaceuticalRegistry\Infrastructure\Console\StorageRegistryImportFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorageRegistryImportFilesTest extends TestCase
{
    private string $projectDir;
    private string $current;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/kiveto-registry-files-' . bin2hex(random_bytes(6));
        $this->current    = $this->projectDir . '/storage/pharma-registry/france/current';
        mkdir($this->current, 0o755, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->projectDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);

            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($this->projectDir);
    }

    public function testTheRegistryFileAndItsDictionaryAreWaiting(): void
    {
        file_put_contents($this->current . '/amm.xml', '<amm/>');
        file_put_contents($this->current . '/dict.xml', '<dict/>');
        touch($this->current . '/amm.xml', 1793944800);

        $files = $this->files()->current('ANMV');

        self::assertNotNull($files);
        self::assertSame([$this->current . '/amm.xml', $this->current . '/dict.xml', '2026-11-06 06:00:00', 'UTC'], [
            $files['file'], $files['dictionary'], $files['modifiedAt']->format('Y-m-d H:i:s'), $files['modifiedAt']->getTimezone()->getName(),
        ]);
        self::assertSame([hash('sha256', '<amm/>'), 6, hash('sha256', '<dict/>'), 7], [$files['fileHash'], $files['fileSize'], $files['dictionaryHash'], $files['dictionarySize']]);
    }

    public function testAKeptFileIsFoundInTheArchiveOnly(): void
    {
        file_put_contents($this->current . '/amm.xml', '<amm/>');
        file_put_contents($this->current . '/dict.xml', '<dict/>');
        $kept = $this->files()->archive('ANMV', new \DateTimeImmutable('2026-11-06 06:30:00', new \DateTimeZone('UTC')));

        self::assertSame(['file' => 'archive/2026-11-06/amm.xml', 'dictionary' => 'archive/2026-11-06/dict.xml'], $kept);
        self::assertSame(realpath($this->projectDir . '/storage/pharma-registry/france/archive/2026-11-06/amm.xml'), $this->files()->kept('ANMV', $kept['file']));
        self::assertNull($this->files()->kept('ANMV', 'archive/2026-11-06/gone.xml'), 'gone');
        file_put_contents($this->current . '/amm.xml', '<next/>');
        self::assertNull($this->files()->kept('ANMV', 'current/amm.xml'), 'not in the archive');
        self::assertNull($this->files()->kept('ANMV', 'archive/../current/amm.xml'), 'no way out of the archive');
        self::assertNull($this->files()->kept('EMA_UPD', 'archive/2026-11-06/amm.xml'), 'a source without an archive');
    }

    #[DataProvider('provideNothingWaitsWithoutBothFilesCases')]
    public function testNothingWaitsWithoutBothFiles(?string $present): void
    {
        if (null !== $present) {
            file_put_contents($this->current . '/' . $present, '<x/>');
        }

        self::assertNull($this->files()->current('ANMV'));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function provideNothingWaitsWithoutBothFilesCases(): iterable
    {
        yield 'no file' => [null];

        yield 'no dictionary' => ['amm.xml'];

        yield 'no registry file' => ['dict.xml'];
    }

    public function testTheImportedFilesAreArchivedByDay(): void
    {
        file_put_contents($this->current . '/amm.xml', '<amm/>');
        file_put_contents($this->current . '/dict.xml', '<dict/>');

        $this->files()->archive('ANMV', new \DateTimeImmutable('2026-11-06 06:30:00', new \DateTimeZone('UTC')));

        $archive = $this->projectDir . '/storage/pharma-registry/france/archive/2026-11-06';
        self::assertSame(['<amm/>', '<dict/>'], [file_get_contents($archive . '/amm.xml'), file_get_contents($archive . '/dict.xml')]);
        self::assertNull($this->files()->current('ANMV'), 'nothing waits any more');
    }

    public function testASecondImportTheSameDayIsArchivedApart(): void
    {
        $at = new \DateTimeImmutable('2026-11-06 06:30:00', new \DateTimeZone('UTC'));
        file_put_contents($this->current . '/amm.xml', '<first/>');
        $this->files()->archive('ANMV', $at);
        file_put_contents($this->current . '/amm.xml', '<second/>');
        $kept = $this->files()->archive('ANMV', $at->modify('+1 hour'));

        $archive = $this->projectDir . '/storage/pharma-registry/france/archive/2026-11-06';
        self::assertSame(['<first/>', '<second/>'], [file_get_contents($archive . '/amm.xml'), file_get_contents($archive . '_2/amm.xml')]);
        self::assertSame('archive/2026-11-06_2/amm.xml', $kept['file']);
    }

    #[DataProvider('provideEachSourceHasItsFolderCases')]
    public function testEachSourceHasItsFolder(string $source, string $folder): void
    {
        $current = $this->projectDir . '/storage/pharma-registry/' . $folder . '/current';
        mkdir($current, 0o755, true);
        file_put_contents($current . '/amm.xml', '<amm/>');
        file_put_contents($current . '/dict.xml', '<dict/>');

        self::assertSame($current . '/amm.xml', $this->files()->current($source)['file'] ?? null);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideEachSourceHasItsFolderCases(): iterable
    {
        yield 'EMA' => ['EMA_UPD', 'europe'];

        yield 'Swissmedic' => ['SWISSMEDIC', 'switzerland'];

        yield 'MHRA' => ['MHRA', 'united-kingdom'];

        yield 'AEMPS' => ['AEMPS', 'spain'];

        yield 'BfArM' => ['BFARM', 'germany'];
    }

    private function files(): StorageRegistryImportFiles
    {
        return new StorageRegistryImportFiles(new ImportFileManager($this->projectDir));
    }
}
