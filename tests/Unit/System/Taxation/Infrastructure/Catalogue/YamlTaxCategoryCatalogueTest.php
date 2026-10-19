<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Infrastructure\Catalogue;

use App\System\Taxation\Application\Command\RegisterTaxCategory\RegisterTaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryNature;
use App\System\Taxation\Infrastructure\Catalogue\YamlTaxCategoryCatalogue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class YamlTaxCategoryCatalogueTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/tax-categories-' . bin2hex(random_bytes(4)) . '.yaml';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testShippedCatalogueSetsTheNatureOfEveryCategory(): void
    {
        $commands = new YamlTaxCategoryCatalogue(\dirname(__DIR__, 6))->commands();

        self::assertCount(19, $commands);

        foreach ($commands as $command) {
            $code     = $command->code->toString();
            $services = str_starts_with($code, 'veterinary.act.') || str_starts_with($code, 'veterinary.service.');

            self::assertSame($services ? TaxCategoryNature::SERVICES : TaxCategoryNature::GOODS, $command->nature, $code);
        }

        $byCode = array_combine(
            array_map(static fn (RegisterTaxCategory $c): string => $c->code->toString(), $commands),
            $commands,
        );
        self::assertSame('Médicaments animaux de compagnie', $byCode['veterinary.drug.companion']->displayName);
        self::assertSame('Médicaments vétérinaires pour animaux de compagnie (taux réduit)', $byCode['veterinary.drug.companion']->description);
        self::assertNull($byCode['veterinary.act.consultation']->description);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMalformedCataloguesAreRefusedCases(): iterable
    {
        yield 'missing nature' => ["categories:\n  - code: veterinary.act.x\n    displayName: X\n"];
        yield 'unknown nature' => ["categories:\n  - code: veterinary.act.x\n    displayName: X\n    nature: both\n"];
        yield 'nature not a string' => ["categories:\n  - code: veterinary.act.x\n    displayName: X\n    nature: [goods]\n"];
        yield 'no categories key' => ["things: []\n"];
        yield 'not a mapping' => ["just text\n"];
        yield 'category without code' => ["categories:\n  - displayName: X\n    nature: goods\n"];
        yield 'category not a mapping' => ["categories:\n  - veterinary.act.x\n"];
    }

    #[DataProvider('provideMalformedCataloguesAreRefusedCases')]
    public function testMalformedCataloguesAreRefused(string $yaml): void
    {
        file_put_contents($this->file, $yaml);

        $this->expectException(\UnexpectedValueException::class);

        new YamlTaxCategoryCatalogue('/unused')->parse($this->file);
    }

    public function testNonStringDescriptionIsDropped(): void
    {
        file_put_contents($this->file, "categories:\n  - code: veterinary.act.x\n    displayName: X\n    description: 12\n    nature: services\n");

        $commands = new YamlTaxCategoryCatalogue('/unused')->parse($this->file);

        self::assertNull($commands[0]->description);
        self::assertSame(TaxCategoryNature::SERVICES, $commands[0]->nature);
    }
}
