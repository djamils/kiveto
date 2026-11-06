<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\SecondFactor;

use App\System\IdentityAccess\Domain\SecondFactor\Base32;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Base32Test extends TestCase
{
    /**
     * RFC 4648 §10, without the padding authenticator apps do not want.
     */
    #[DataProvider('provideEncodesTheRfc4648VectorsCases')]
    public function testEncodesTheRfc4648Vectors(string $bytes, string $encoded): void
    {
        self::assertSame($encoded, Base32::encode($bytes));
        self::assertSame($bytes, Base32::decode($encoded));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideEncodesTheRfc4648VectorsCases(): iterable
    {
        yield 'empty' => ['', ''];

        yield 'f' => ['f', 'MY'];

        yield 'fo' => ['fo', 'MZXQ'];

        yield 'foo' => ['foo', 'MZXW6'];

        yield 'foob' => ['foob', 'MZXW6YQ'];

        yield 'fooba' => ['fooba', 'MZXW6YTB'];

        yield 'foobar' => ['foobar', 'MZXW6YTBOI'];

        yield 'RFC 6238 key' => ['12345678901234567890', 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'];
    }

    public function testDecodingIgnoresCaseSpacesAndPadding(): void
    {
        self::assertSame('foobar', Base32::decode('mzxw 6ytb oi======'));
        self::assertSame('', Base32::decode(' = '));
    }

    #[DataProvider('provideRefusesATextOutsideTheAlphabetCases')]
    public function testRefusesATextOutsideTheAlphabet(string $text): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not a base32 text.');

        Base32::decode($text);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRefusesATextOutsideTheAlphabetCases(): iterable
    {
        yield 'digit one' => ['MZ1'];

        yield 'digit zero' => ['0'];

        yield 'digit eight' => ['MZXW8'];

        yield 'dash' => ['MZXW-6YTB'];
    }
}
