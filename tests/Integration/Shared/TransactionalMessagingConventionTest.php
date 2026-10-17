<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Source-level guards for the implicit outbox (see src/Shared/README.md).
 */
final class TransactionalMessagingConventionTest extends TestCase
{
    public function testNoDispatchAfterCurrentBus(): void
    {
        $root   = \dirname(__DIR__, 3);
        $finder = new Finder()->files()->in([$root . '/src', $root . '/config'])->name(['*.php', '*.yaml', '*.yml', '*.xml']);

        $offenders = [];

        foreach ($finder as $file) {
            $content = $file->getContents();

            if (str_contains($content, 'DispatchAfterCurrentBusStamp') || str_contains($content, 'dispatch_after_current_bus')) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        self::assertSame([], $offenders, 'Integration events must be written inside the business transaction.');
    }

    public function testIntegrationBridgesListenOnTheSyncEventBus(): void
    {
        $finder = new Finder()->files()->in(\dirname(__DIR__, 3) . '/src')->name('*IntegrationBridge.php');

        $bridges   = 0;
        $offenders = [];

        foreach ($finder as $file) {
            ++$bridges;
            $content = $file->getContents();

            if (!str_contains($content, "#[AsMessageHandler(bus: 'messenger.bus.event')]") || str_contains($content, 'fromTransport')) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        self::assertGreaterThan(0, $bridges);
        self::assertSame([], $offenders, 'Bridges must run synchronously on messenger.bus.event.');
    }
}
