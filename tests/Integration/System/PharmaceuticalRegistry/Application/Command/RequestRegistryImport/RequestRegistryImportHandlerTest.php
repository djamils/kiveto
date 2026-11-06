<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport;

use App\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport\RequestRegistryImport;
use App\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport\RequestRegistryImportHandler;
use App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle\RunFullImportCycleHandler;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use App\Tests\Shared\Time\FrozenClock;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * « Lancer l'import » runs the real import cycle on the sample files of the
 * ANMV parser tests (ten products), then archives them.
 */
final class RequestRegistryImportHandlerTest extends KernelTestCase
{
    public function testTheWaitingFilesAreImportedThenArchived(): void
    {
        $fixtures = \dirname(__DIR__, 6) . '/fixtures/System/PharmaceuticalRegistry';
        $files    = new class($fixtures . '/anmv_sample.xml', $fixtures . '/dictionary_sample.xml') implements RegistryImportFilesInterface {
            /** @var list<array{string, \DateTimeImmutable}> */
            public array $archived = [];

            public function __construct(private readonly string $file, private readonly string $dictionary)
            {
            }

            /**
             * @return array{file: string, dictionary: string, modifiedAt: \DateTimeImmutable}
             */
            public function current(string $source): array
            {
                return ['file' => $this->file, 'dictionary' => $this->dictionary, 'modifiedAt' => new \DateTimeImmutable('2026-11-06 06:00:00')];
            }

            public function archive(string $source, \DateTimeImmutable $at): void
            {
                $this->archived[] = [$source, $at];
            }
        };
        $clock          = new FrozenClock(new \DateTimeImmutable('2026-11-06 06:30:00', new \DateTimeZone('UTC')));
        $authorizations = $this->imports()->authorizationCount();

        self::assertTrue($this->handler($files, $clock)(new RequestRegistryImport('ANMV')));

        self::assertSame([['ANMV', $clock->now()]], $files->archived);
        $latest = $this->imports()->imports('ANMV', 1)[0] ?? null;
        self::assertNotNull($latest);
        self::assertSame(['APPLIED', 10], [$latest->status, $latest->created]);
        self::assertSame($authorizations + 10, $this->imports()->authorizationCount());
    }

    public function testNothingRunsWithoutFiles(): void
    {
        $files = new class implements RegistryImportFilesInterface {
            public int $archives = 0;

            public function current(string $source): ?array
            {
                return null;
            }

            public function archive(string $source, \DateTimeImmutable $at): void
            {
                ++$this->archives;
            }
        };
        $before = \count($this->imports()->imports('ANMV', 1000));

        self::assertFalse($this->handler($files, new FrozenClock(new \DateTimeImmutable()))(new RequestRegistryImport()));

        self::assertSame(0, $files->archives);
        self::assertCount($before, $this->imports()->imports('ANMV', 1000), 'no import ran');
    }

    private function handler(RegistryImportFilesInterface $files, FrozenClock $clock): RequestRegistryImportHandler
    {
        $cycle = self::getContainer()->get(RunFullImportCycleHandler::class);
        \assert($cycle instanceof RunFullImportCycleHandler);

        return new RequestRegistryImportHandler($files, $cycle, $clock);
    }

    private function imports(): RegistryImportReadRepositoryInterface
    {
        $repository = self::getContainer()->get(RegistryImportReadRepositoryInterface::class);
        \assert($repository instanceof RegistryImportReadRepositoryInterface);

        return $repository;
    }
}
