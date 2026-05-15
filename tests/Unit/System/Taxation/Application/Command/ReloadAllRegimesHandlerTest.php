<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Application\Command;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Taxation\Application\Command\LoadTaxRegime\LoadTaxRegime;
use App\System\Taxation\Application\Command\ReloadAllRegimes\ReloadAllRegimes;
use App\System\Taxation\Application\Command\ReloadAllRegimes\ReloadAllRegimesHandler;
use App\System\Taxation\Infrastructure\RegimeLoader\RegimeFileLocator;
use PHPUnit\Framework\TestCase;

final class ReloadAllRegimesHandlerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/taxation_test_reload_' . uniqid();
        $regimesDir   = $this->tmpDir . '/src/System/Taxation/Infrastructure/Resources/regimes';
        mkdir($regimesDir, 0o755, true);

        // Create stub YAML files for fr and ch (content not parsed by handler)
        file_put_contents($regimesDir . '/fr.yaml', 'id: FR');
        file_put_contents($regimesDir . '/ch.yaml', 'id: CH');
    }

    protected function tearDown(): void
    {
        /** @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->tmpDir);
    }

    public function testDispatchesLoadTaxRegimeForEachFile(): void
    {
        $locator = new RegimeFileLocator($this->tmpDir);

        $dispatchedCommands = [];
        $commandBus         = $this->createMock(CommandBusInterface::class);
        $commandBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $command) use (&$dispatchedCommands): null {
                $dispatchedCommands[] = $command;

                return null;
            })
        ;

        $handler = new ReloadAllRegimesHandler($locator, $commandBus);
        $handler(new ReloadAllRegimes());

        self::assertCount(2, $dispatchedCommands);

        $regimeIds = array_map(
            static fn (object $cmd): string => $cmd instanceof LoadTaxRegime ? $cmd->regimeId->toString() : '',
            $dispatchedCommands,
        );
        sort($regimeIds);

        self::assertSame(['CH', 'FR'], $regimeIds);
    }
}
