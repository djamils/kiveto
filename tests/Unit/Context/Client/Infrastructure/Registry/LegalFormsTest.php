<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Registry;

use App\Context\Client\Infrastructure\Registry\LegalForms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegalForms::class)]
final class LegalFormsTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function provideACodeGetsItsLabelCases(): iterable
    {
        yield 'EARL' => ['6598', 'EARL'];
        yield 'GAEC' => ['6533', 'GAEC'];
        yield 'SCEA' => ['6597', 'SCEA'];
        yield 'SARL' => ['5499', 'SARL'];
        yield 'SAS' => ['5710', 'SAS'];
        yield 'SA' => ['5599', 'SA'];
        yield 'SCI' => ['6540', 'SCI'];
        yield 'EI' => ['1000', 'EI'];
        yield 'association' => ['9220', 'Association'];
        yield 'INSEE label' => ['5458', 'SARL coopérative de production (SCOP)'];
        yield 'foundation' => ['9300', 'Fondation'];
        yield 'unknown code' => ['4242', '4242'];
        yield 'padded code' => [' 5710 ', 'SAS'];
        yield 'none' => [null, null];
        yield 'empty' => ['', null];
    }

    #[DataProvider('provideACodeGetsItsLabelCases')]
    public function testACodeGetsItsLabel(?string $code, ?string $label): void
    {
        self::assertSame($label, LegalForms::label($code));
    }
}
