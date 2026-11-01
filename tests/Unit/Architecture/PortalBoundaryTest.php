<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The Portal reads the other contexts only through their query and command
 * buses: its code never imports another context's Domain or Infrastructure,
 * except the integration events it consumes. The Portal API (Presentation)
 * may still name the domain exceptions it maps to problems (any `Domain\\…\\*Exception`).
 */
final class PortalBoundaryTest extends TestCase
{
    /** Contexts written for the Portal: directory → their own namespace. */
    private const array CONTEXTS = [
        'src/Context/ClientPortal' => 'App\\Context\\ClientPortal\\',
    ];

    private const string API = 'src/Presentation/Api/Portal';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAContextNeverImportsAnotherContextsInternalsCases(): iterable
    {
        foreach (self::CONTEXTS as $directory => $namespace) {
            yield $directory => [$directory, $namespace];
        }
    }

    #[DataProvider('provideAContextNeverImportsAnotherContextsInternalsCases')]
    public function testAContextNeverImportsAnotherContextsInternals(string $directory, string $ownNamespace): void
    {
        $offences = [];

        foreach (self::imports($directory) as $file => $import) {
            if (str_starts_with($import, $ownNamespace) || str_ends_with($import, 'IntegrationEvent')) {
                continue;
            }

            if (1 === preg_match('/^App\\\\(Context|System)\\\\[A-Za-z]+\\\\(Domain|Infrastructure)\\\\/', $import)) {
                $offences[] = $file . ': ' . $import;
            }
        }

        self::assertSame([], $offences);
    }

    public function testThePortalApiImportsNoContextInternalsButDomainExceptions(): void
    {
        $offences = [];

        foreach (self::imports(self::API) as $file => $import) {
            $internal = 1 === preg_match('/^App\\\\(Context|System)\\\\[A-Za-z]+\\\\(Domain|Infrastructure)\\\\/', $import);

            if ($internal && 1 !== preg_match('/^App\\\\(Context|System)\\\\[A-Za-z]+\\\\Domain\\\\[A-Za-z\\\\]*Exception$/', $import)) {
                $offences[] = $file . ': ' . $import;
            }
        }

        self::assertSame([], $offences);
    }

    /**
     * @return iterable<string, string> file → imported class, one pair per `use`
     */
    private static function imports(string $directory): iterable
    {
        $root  = \dirname(__DIR__, 3) . '/';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . $directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);

            if ('php' !== $file->getExtension()) {
                continue;
            }

            preg_match_all('/^use (App\\\\[A-Za-z0-9_\\\\]+)/m', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $import) {
                yield substr($file->getPathname(), \strlen($root)) => $import;
            }
        }
    }
}
