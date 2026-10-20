<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Format;

use App\System\ElectronicInvoicing\Infrastructure\Format\XsdValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * AC 19: every AFNOR CII invoice / credit-note sample (UC1–UC5, UC4b, UC5b,
 * UC5c) passes the vendored UN/CEFACT CII D22B XSD.
 *
 * The samples are copied from docs/e-invoicing/AFNOR_Version_FR (not tracked)
 * into tests/Fixtures/electronic-invoicing/afnor, « - Copia » duplicates
 * excluded. No sample is excluded from this test.
 */
final class AfnorSamplesXsdTest extends TestCase
{
    public const string SCHEMA = __DIR__ . '/../../../../../../src/System/ElectronicInvoicing/Infrastructure/Resources/xsd/cii-d22b/uncefact/CrossIndustryInvoice_100pD22B.xsd';
    public const string AFNOR  = __DIR__ . '/../../../../../Fixtures/electronic-invoicing/afnor';

    #[DataProvider('provideAfnorCiiSamplePassesTheXsdCases')]
    public function testAfnorCiiSamplePassesTheXsd(string $file): void
    {
        $xml = file_get_contents($file);
        self::assertIsString($xml);

        self::assertSame([], new XsdValidator()->validate($xml, self::SCHEMA));
    }

    public function testEveryAfnorUseCaseIsCovered(): void
    {
        self::assertCount(8, self::ciiSamples());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAfnorCiiSamplePassesTheXsdCases(): iterable
    {
        foreach (self::ciiSamples() as $file) {
            yield basename($file) => [$file];
        }
    }

    /**
     * @return list<string>
     */
    private static function ciiSamples(): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::AFNOR, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            $path      = $file->getPathname();
            $isCii     = str_contains($file->getFilename(), '_CII_') && str_ends_with($path, '.xml');
            $isUseCase = 1 === preg_match('#/afnor/UC[^/]*/#', $path);
            if ($isCii && $isUseCase && !str_contains($path, ' - Copia')) {
                $files[] = $path;
            }
        }
        sort($files);

        return $files;
    }
}
