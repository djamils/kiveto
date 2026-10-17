<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\InvalidFrenchVatNumberException;
use App\Shared\Domain\ValueObject\Siren;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrenchVatNumberTest extends TestCase
{
    public function testInputIsNormalised(): void
    {
        $vat = FrenchVatNumber::fromInput('fr 31 523.456.788', Siren::fromInput('523456788'));

        self::assertSame('FR31523456788', $vat->value());
        self::assertSame('FR 31 523456788', $vat->formatted());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidInputIsRejectedWithItsReasonCases(): iterable
    {
        yield 'wrong key' => ['FR23523456788', InvalidFrenchVatNumberException::KEY];
        yield 'other SIREN' => ['FR31123456789', InvalidFrenchVatNumberException::SIREN_MISMATCH];
        yield 'too short' => ['FR3152345678', InvalidFrenchVatNumberException::FORMAT];
        yield 'alphabetic key' => ['FRAB523456788', InvalidFrenchVatNumberException::FORMAT];
        yield 'other country' => ['DE31523456788', InvalidFrenchVatNumberException::FORMAT];
    }

    #[DataProvider('provideInvalidInputIsRejectedWithItsReasonCases')]
    public function testInvalidInputIsRejectedWithItsReason(string $input, string $reason): void
    {
        try {
            FrenchVatNumber::fromInput($input, Siren::fromInput('523456788'));
            self::fail('Expected an InvalidFrenchVatNumberException.');
        } catch (InvalidFrenchVatNumberException $exception) {
            self::assertSame($reason, $exception->reason);
            self::assertStringContainsString($reason, $exception->getMessage());
        }
    }

    public function testForSirenComputesTheKey(): void
    {
        self::assertSame('FR31523456788', FrenchVatNumber::forSiren(Siren::fromInput('523456788'))->value());
        self::assertSame('FR14441569738', FrenchVatNumber::forSiren(Siren::fromInput('441569738'))->value());
        self::assertSame('FR39356000000', FrenchVatNumber::forSiren(Siren::fromInput('356000000'))->value());
    }

    public function testEquality(): void
    {
        $siren = Siren::fromInput('523456788');

        self::assertTrue(FrenchVatNumber::forSiren($siren)->equals(FrenchVatNumber::fromInput('FR31523456788', $siren)));
        self::assertFalse(FrenchVatNumber::forSiren($siren)->equals(FrenchVatNumber::forSiren(Siren::fromInput('441569738'))));
    }
}
