<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Infrastructure\RegimeLoader;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Domain\Exception\OverlappingValidityPeriodsException;
use App\System\Taxation\Domain\Exception\UnknownCategoryPatternException;
use App\System\Taxation\Domain\Repository\TaxCategoryRepositoryInterface;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Infrastructure\RegimeLoader\RegimeFileLocator;
use App\System\Taxation\Infrastructure\RegimeLoader\YamlTaxRegimeLoader;
use PHPUnit\Framework\TestCase;

final class YamlTaxRegimeLoaderTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
    }

    public function testParsesFrYamlCorrectly(): void
    {
        // Use the real project directory so RegimeFileLocator resolves fr.yaml correctly
        $projectDir = __DIR__ . '/../../../../../../';
        $locator    = new RegimeFileLocator($projectDir);

        // All category patterns used in fr.yaml are exact (no wildcards at top level),
        // but some entries are exact codes — registry returns true for all
        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        // fr.yaml has no wildcard patterns, so findAllActive won't be called
        $categoryRepo = $this->createStub(TaxCategoryRepositoryInterface::class);
        $categoryRepo->method('findAllActive')->willReturn([]);

        $loader    = $this->makeLoader($locator, $categoryRegistry, $categoryRepo);
        $blueprint = $loader->load(TaxRegimeId::fromString('FR'));

        // fr.yaml has 4 rates
        self::assertCount(4, $blueprint->rates);
        // fr.yaml has 2 mention templates
        self::assertCount(2, $blueprint->mentionTemplates);

        $rate0 = $blueprint->rates[0];
        self::assertSame('fr.standard', $rate0->id()->toString());
        self::assertSame(2000, $rate0->value()->basisPoints());

        $rate2 = $blueprint->rates[2];
        self::assertSame('fr.super_reduced', $rate2->id()->toString());
        self::assertSame(550, $rate2->value()->basisPoints());

        self::assertSame('INTRACOM_REVERSE_CHARGE', $blueprint->mentionTemplates[0]->code());
    }

    public function testUnknownCategoryPatternThrows(): void
    {
        $yaml = <<<YAML
id: FR
name: "Test Regime"
country: FR
active: true
rates:
  - id: fr.standard
    value: "20.0"
    validFrom: "2020-01-01"
    validTo: null
    appliesTo:
      categories: ["veterinary.unknown.*"]
      conditions:
        regions: []
        customerStatuses: []
        animalUsages: []
        clinicLiability: null
mentionTemplates: []
YAML;

        [$locator, $tmpDir] = $this->makeLocatorWithYaml($yaml);

        // "veterinary.unknown.*" is a wildcard → goes to repo, which returns empty list → throws
        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);

        $categoryRepo = $this->createStub(TaxCategoryRepositoryInterface::class);
        $categoryRepo->method('findAllActive')->willReturn([]);

        $loader = $this->makeLoader($locator, $categoryRegistry, $categoryRepo);

        $this->expectException(UnknownCategoryPatternException::class);

        try {
            $loader->load(TaxRegimeId::fromString('FR'));
        } finally {
            $this->removeTempDir($tmpDir);
        }
    }

    public function testOverlappingValidityPeriodsThrows(): void
    {
        // Two rates with identical (empty) conditions and overlapping periods
        $yaml = <<<YAML
id: FR
name: "Test Regime"
country: FR
active: true
rates:
  - id: fr.rate_a
    value: "20.0"
    validFrom: "2020-01-01"
    validTo: null
    appliesTo:
      categories: []
      conditions:
        regions: []
        customerStatuses: []
        animalUsages: []
        clinicLiability: null
  - id: fr.rate_b
    value: "10.0"
    validFrom: "2021-01-01"
    validTo: null
    appliesTo:
      categories: []
      conditions:
        regions: []
        customerStatuses: []
        animalUsages: []
        clinicLiability: null
mentionTemplates: []
YAML;

        [$locator, $tmpDir] = $this->makeLocatorWithYaml($yaml);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $categoryRepo = $this->createStub(TaxCategoryRepositoryInterface::class);
        $categoryRepo->method('findAllActive')->willReturn([]);

        $loader = $this->makeLoader($locator, $categoryRegistry, $categoryRepo);

        $this->expectException(OverlappingValidityPeriodsException::class);

        try {
            $loader->load(TaxRegimeId::fromString('FR'));
        } finally {
            $this->removeTempDir($tmpDir);
        }
    }

    public function testNonOverlappingPeriodsDoNotThrow(): void
    {
        // rate1 ends 2020-12-31, rate2 starts 2021-01-01 — consecutive, non-overlapping
        $yaml = <<<YAML
id: FR
name: "Test Regime"
country: FR
active: true
rates:
  - id: fr.rate_a
    value: "20.0"
    validFrom: "2014-01-01"
    validTo: "2020-12-31"
    appliesTo:
      categories: []
      conditions:
        regions: []
        customerStatuses: []
        animalUsages: []
        clinicLiability: null
  - id: fr.rate_b
    value: "18.0"
    validFrom: "2021-01-01"
    validTo: null
    appliesTo:
      categories: []
      conditions:
        regions: []
        customerStatuses: []
        animalUsages: []
        clinicLiability: null
mentionTemplates: []
YAML;

        [$locator, $tmpDir] = $this->makeLocatorWithYaml($yaml);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $categoryRepo = $this->createStub(TaxCategoryRepositoryInterface::class);
        $categoryRepo->method('findAllActive')->willReturn([]);

        $loader    = $this->makeLoader($locator, $categoryRegistry, $categoryRepo);
        $blueprint = $loader->load(TaxRegimeId::fromString('FR'));

        self::assertCount(2, $blueprint->rates);

        $this->removeTempDir($tmpDir);
    }

    public function testUnknownNonWildcardCategoryThrows(): void
    {
        $yaml = <<<YAML
id: FR
name: "Test Regime"
country: FR
active: true
rates:
  - id: fr.standard
    value: "20.0"
    validFrom: "2020-01-01"
    validTo: null
    appliesTo:
      categories: ["veterinary.unknown.exact"]
      conditions:
        regions: []
        customerStatuses: []
        animalUsages: []
        clinicLiability: null
mentionTemplates: []
YAML;

        [$locator, $tmpDir] = $this->makeLocatorWithYaml($yaml);

        // Non-wildcard → goes to categoryRegistry->has(), which returns false → throws
        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(false);

        $categoryRepo = $this->createStub(TaxCategoryRepositoryInterface::class);

        $loader = $this->makeLoader($locator, $categoryRegistry, $categoryRepo);

        $this->expectException(UnknownCategoryPatternException::class);

        try {
            $loader->load(TaxRegimeId::fromString('FR'));
        } finally {
            $this->removeTempDir($tmpDir);
        }
    }

    public function testWildcardCategoryMatchingAtLeastOneActiveCategorySucceeds(): void
    {
        $yaml = <<<YAML
id: FR
name: "Test Regime"
country: FR
active: true
rates:
  - id: fr.standard
    value: "20.0"
    validFrom: "2020-01-01"
    validTo: null
    appliesTo:
      categories: ["veterinary.act.*"]
      conditions:
        regions: []
        customerStatuses: []
        animalUsages: []
        clinicLiability: null
mentionTemplates: []
YAML;

        [$locator, $tmpDir] = $this->makeLocatorWithYaml($yaml);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);

        // Provide a real TaxCategory whose code matches "veterinary.act.*"
        $now          = new \DateTimeImmutable('2024-01-01');
        $realCategory = TaxCategory::reconstitute(
            TaxCategoryCode::fromString('veterinary.act.consultation'),
            'Consultation',
            null,
            true,
            $now,
            $now,
        );

        $categoryRepo = $this->createStub(TaxCategoryRepositoryInterface::class);
        $categoryRepo->method('findAllActive')->willReturn([$realCategory]);

        $loader    = $this->makeLoader($locator, $categoryRegistry, $categoryRepo);
        $blueprint = $loader->load(TaxRegimeId::fromString('FR'));

        self::assertCount(1, $blueprint->rates);

        $this->removeTempDir($tmpDir);
    }

    public function testClinicLiabilityInRateConditionIsParsed(): void
    {
        $yaml = <<<YAML
id: FR
name: "Test Regime"
country: FR
active: true
rates:
  - id: fr.liable
    value: "20.0"
    validFrom: "2020-01-01"
    validTo: null
    appliesTo:
      categories: ["veterinary.act.consultation"]
      conditions:
        regions: []
        customerStatuses: []
        animalUsages: []
        clinicLiability: "liable"
mentionTemplates: []
YAML;

        [$locator, $tmpDir] = $this->makeLocatorWithYaml($yaml);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $categoryRepo = $this->createStub(TaxCategoryRepositoryInterface::class);

        $loader    = $this->makeLoader($locator, $categoryRegistry, $categoryRepo);
        $blueprint = $loader->load(TaxRegimeId::fromString('FR'));

        self::assertCount(1, $blueprint->rates);
        $condition = $blueprint->rates[0]->appliesTo();
        self::assertNotNull($condition->clinicLiability());

        $this->removeTempDir($tmpDir);
    }

    public function testClinicLiabilityInMentionTemplateConditionIsParsed(): void
    {
        $yaml = <<<YAML
id: FR
name: "Test Regime"
country: FR
active: true
rates: []
mentionTemplates:
  - code: NOT_LIABLE_MENTION
    template: "Exonération article 261 CGI"
    locale: fr_FR
    condition:
      regions: []
      customerStatuses: []
      animalUsages: []
      clinicLiability: "not_liable"
YAML;

        [$locator, $tmpDir] = $this->makeLocatorWithYaml($yaml);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRepo     = $this->createStub(TaxCategoryRepositoryInterface::class);

        $loader    = $this->makeLoader($locator, $categoryRegistry, $categoryRepo);
        $blueprint = $loader->load(TaxRegimeId::fromString('FR'));

        self::assertCount(1, $blueprint->mentionTemplates);
        $condition = $blueprint->mentionTemplates[0]->condition();
        self::assertNotNull($condition->clinicLiability());

        $this->removeTempDir($tmpDir);
    }

    private function makeLoader(
        RegimeFileLocator $locator,
        TaxCategoryRegistryInterface $categoryRegistry,
        TaxCategoryRepositoryInterface $categoryRepo,
    ): YamlTaxRegimeLoader {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturnCallback(
            static function (): string {
                return \sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    random_int(0, 0xFFFF),
                    random_int(0, 0xFFFF),
                    random_int(0, 0xFFFF),
                    random_int(0, 0x0FFF) | 0x4000,
                    random_int(0, 0x3FFF) | 0x8000,
                    random_int(0, 0xFFFF),
                    random_int(0, 0xFFFF),
                    random_int(0, 0xFFFF),
                );
            }
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($this->now);

        return new YamlTaxRegimeLoader($locator, $categoryRegistry, $categoryRepo, $uuidGenerator, $clock);
    }

    /**
     * Writes $yaml as <regimeId>.yaml inside a temp project structure and returns
     * the RegimeFileLocator pointing to that temp project dir.
     * Also returns the full path to the written file so it can be unlinked.
     *
     * @return array{RegimeFileLocator, string}
     */
    private function makeLocatorWithYaml(string $yaml, string $regimeId = 'FR'): array
    {
        $tmpDir     = sys_get_temp_dir() . '/taxation_test_' . uniqid();
        $regimesDir = $tmpDir . '/src/System/Taxation/Infrastructure/Resources/regimes';
        mkdir($regimesDir, 0o755, true);

        $filePath = $regimesDir . '/' . strtolower($regimeId) . '.yaml';
        file_put_contents($filePath, $yaml);

        $locator = new RegimeFileLocator($tmpDir);

        return [$locator, $tmpDir];
    }

    /**
     * Recursively removes a directory and its contents.
     */
    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        /** @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
