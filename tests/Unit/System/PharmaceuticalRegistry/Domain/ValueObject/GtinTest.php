<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain\ValueObject;

use App\System\PharmaceuticalRegistry\Domain\Exception\InvalidGtinException;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Gtin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GtinTest extends TestCase
{
    #[DataProvider('provideAcceptsValidGtinsCases')]
    public function testAcceptsValidGtins(string $gtin): void
    {
        self::assertSame($gtin, Gtin::fromString($gtin)->toString());
    }

    /** @return array<array{string}> */
    public static function provideAcceptsValidGtinsCases(): iterable
    {
        return [['12345678'], ['1234567890123'], ['12345678901234']];
    }

    #[DataProvider('provideRejectsInvalidGtinsCases')]
    public function testRejectsInvalidGtins(string $gtin): void
    {
        $this->expectException(InvalidGtinException::class);
        Gtin::fromString($gtin);
    }

    /** @return array<array{string}> */
    public static function provideRejectsInvalidGtinsCases(): iterable
    {
        return [['1234567'], ['123456789012345'], ['abc12345678']];
    }
}
