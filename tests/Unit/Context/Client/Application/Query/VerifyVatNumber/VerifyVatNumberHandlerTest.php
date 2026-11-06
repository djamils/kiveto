<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\VerifyVatNumber;

use App\Context\Client\Application\Port\VatNumberVerifierInterface;
use App\Context\Client\Application\Port\VatVerificationResult;
use App\Context\Client\Application\Query\VerifyVatNumber\VatNumberCheck;
use App\Context\Client\Application\Query\VerifyVatNumber\VerifyVatNumber;
use App\Context\Client\Application\Query\VerifyVatNumber\VerifyVatNumberHandler;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VerifyVatNumberHandlerTest extends TestCase
{
    #[DataProvider('provideVatInformationExchangeSystemAnswersCases')]
    public function testVatInformationExchangeSystemAnswers(bool $registered, VatNumberCheck $check): void
    {
        $verifier = $this->createMock(VatNumberVerifierInterface::class);
        $verifier->expects(self::once())->method('verify')->with('FR31523456788')->willReturn(new VatVerificationResult($registered));

        self::assertSame($check, new VerifyVatNumberHandler($verifier)(new VerifyVatNumber(' fr 31 523 456 788 ')));
    }

    /**
     * @return iterable<string, array{bool, VatNumberCheck}>
     */
    public static function provideVatInformationExchangeSystemAnswersCases(): iterable
    {
        yield 'registered' => [true, VatNumberCheck::VALID];

        yield 'not registered' => [false, VatNumberCheck::INVALID];
    }

    public function testNothingIsKnownWhenTheServiceDoesNotAnswer(): void
    {
        $verifier = $this->createMock(VatNumberVerifierInterface::class);
        $verifier->expects(self::once())->method('verify')->willThrowException(ExternalServiceUnavailable::unreachable('VIES'));

        self::assertSame(VatNumberCheck::UNAVAILABLE, new VerifyVatNumberHandler($verifier)(new VerifyVatNumber('FR31523456788')));
    }

    #[DataProvider('provideAMalformedNumberIsInvalidWithoutAskingCases')]
    public function testAMalformedNumberIsInvalidWithoutAsking(string $number): void
    {
        $verifier = $this->createMock(VatNumberVerifierInterface::class);
        $verifier->expects(self::never())->method('verify');

        self::assertSame(VatNumberCheck::INVALID, new VerifyVatNumberHandler($verifier)(new VerifyVatNumber($number)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAMalformedNumberIsInvalidWithoutAskingCases(): iterable
    {
        yield 'empty' => [''];

        yield 'no country prefix' => ['31523456788'];

        yield 'too short' => ['FR1'];

        yield 'too long' => ['FR12345678901234'];

        yield 'punctuation' => ['FR-31523456788'];
    }
}
