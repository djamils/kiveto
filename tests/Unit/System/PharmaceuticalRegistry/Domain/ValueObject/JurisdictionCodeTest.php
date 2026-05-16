<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain\ValueObject;

use App\System\PharmaceuticalRegistry\Domain\Exception\InvalidJurisdictionCodeException;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JurisdictionCodeTest extends TestCase
{
    #[DataProvider('provideAcceptsValidCodesCases')]
    public function testAcceptsValidCodes(string $code): void
    {
        $jc = JurisdictionCode::fromString($code);

        self::assertSame($code, $jc->toString());
    }

    /** @return array<array{string}> */
    public static function provideAcceptsValidCodesCases(): iterable
    {
        return [['FR'], ['EU'], ['CH'], ['UK'], ['ES'], ['DE']];
    }

    #[DataProvider('provideRejectsInvalidCodesCases')]
    public function testRejectsInvalidCodes(string $code): void
    {
        $this->expectException(InvalidJurisdictionCodeException::class);
        JurisdictionCode::fromString($code);
    }

    /** @return array<array{string}> */
    public static function provideRejectsInvalidCodesCases(): iterable
    {
        return [['france'], ['fr'], ['EU1'], ['F'], ['FRANCE']];
    }

    public function testEquality(): void
    {
        $a = JurisdictionCode::fromString('FR');
        $b = JurisdictionCode::fromString('FR');
        $c = JurisdictionCode::fromString('EU');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
