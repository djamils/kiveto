<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\InvalidSirenException;
use App\Shared\Domain\ValueObject\Siren;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SirenTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInputIsNormalisedCases(): iterable
    {
        yield 'dots and spaces' => [' 523.456.788 ', '523456788'];
        yield 'non-breaking spaces' => ["523\u{00A0}456\u{202F}788", '523456788'];
        yield 'dashes' => ['523-456-788', '523456788'];
        yield 'SIRET of La Poste' => ['35600000000048', '356000000'];
        yield 'formatted SIRET' => ['523 456 788 00012', '523456788'];
    }

    #[DataProvider('provideInputIsNormalisedCases')]
    public function testInputIsNormalised(string $input, string $expected): void
    {
        self::assertSame($expected, Siren::fromInput($input)->value());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidInputIsRejectedWithItsReasonCases(): iterable
    {
        yield 'eight digits' => ['52345678', InvalidSirenException::FORMAT];
        yield 'letters' => ['52345678A', InvalidSirenException::FORMAT];
        yield 'empty' => ['', InvalidSirenException::FORMAT];
        yield 'ten digits' => ['5234567880', InvalidSirenException::FORMAT];
        yield 'bad checksum' => ['523456789', InvalidSirenException::CHECKSUM];
    }

    #[DataProvider('provideInvalidInputIsRejectedWithItsReasonCases')]
    public function testInvalidInputIsRejectedWithItsReason(string $input, string $reason): void
    {
        try {
            Siren::fromInput($input);
            self::fail('Expected an InvalidSirenException.');
        } catch (InvalidSirenException $exception) {
            self::assertSame($reason, $exception->reason);
            self::assertStringContainsString($reason, $exception->getMessage());
        }
    }

    public function testFormattedGroupsDigitsByThree(): void
    {
        self::assertSame('356 000 000', Siren::fromInput('356000000')->formatted());
    }

    public function testEquality(): void
    {
        self::assertTrue(Siren::fromInput('523456788')->equals(Siren::fromInput('523 456 788')));
        self::assertFalse(Siren::fromInput('523456788')->equals(Siren::fromInput('356000000')));
    }
}
