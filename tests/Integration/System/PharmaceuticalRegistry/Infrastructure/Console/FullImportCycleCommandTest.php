<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `app:pharmaceutical-registry:full-import-cycle`: files given on the
 * command line are imported as they are (nothing archived); missing files
 * stop it.
 */
final class FullImportCycleCommandTest extends KernelTestCase
{
    public function testFilesGivenOnTheCommandLineAreImported(): void
    {
        $fixtures = \dirname(__DIR__, 5) . '/fixtures/System/PharmaceuticalRegistry';
        $before   = $this->imports()->authorizationCount();

        $tester = $this->tester();
        $code   = $tester->execute(['--file' => $fixtures . '/anmv_sample.xml', '--dictionary' => $fixtures . '/dictionary_sample.xml']);

        self::assertSame(Command::SUCCESS, $code);
        self::assertStringContainsString('Import complete.', $tester->getDisplay());
        self::assertSame($before + 10, $this->imports()->authorizationCount());
        self::assertSame(['APPLIED', 10], [$this->imports()->imports('ANMV', 1)[0]->status ?? null, $this->imports()->imports('ANMV', 1)[0]->created ?? null]);
    }

    public function testAMissingFileStopsIt(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['--file' => '/nowhere/amm.xml']));
        self::assertStringContainsString('File not found: /nowhere/amm.xml', $tester->getDisplay());

        $fixtures = \dirname(__DIR__, 5) . '/fixtures/System/PharmaceuticalRegistry';
        self::assertSame(Command::FAILURE, $tester->execute(['--file' => $fixtures . '/anmv_sample.xml', '--dictionary' => '/nowhere/dict.xml']));
        self::assertStringContainsString('Dictionary not found: /nowhere/dict.xml', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:pharmaceutical-registry:full-import-cycle'));
    }

    private function imports(): RegistryImportReadRepositoryInterface
    {
        $repository = self::getContainer()->get(RegistryImportReadRepositoryInterface::class);
        \assert($repository instanceof RegistryImportReadRepositoryInterface);

        return $repository;
    }
}
