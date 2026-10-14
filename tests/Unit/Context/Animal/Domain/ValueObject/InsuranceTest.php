<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Domain\ValueObject;

use App\Context\Animal\Domain\ValueObject\Insurance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InsuranceTest extends TestCase
{
    public function testNormalisesTheInsurerAndThePolicyNumber(): void
    {
        $insurance = new Insurance('  Santévet ', ' sv-2210-4471 ');

        self::assertSame('Santévet', $insurance->insurerName);
        self::assertSame('SV-2210-4471', $insurance->policyNumber);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRefusesAnInvalidInsuranceCases(): iterable
    {
        yield 'no insurer' => ['  ', 'SV-2210'];
        yield 'insurer too long' => [str_repeat('a', 81), 'SV-2210'];
        yield 'policy too short' => ['Santévet', 'SV1'];
        yield 'policy too long' => ['Santévet', str_repeat('A', 21)];
        yield 'policy with a space' => ['Santévet', 'SV 2210'];
    }

    #[DataProvider('provideRefusesAnInvalidInsuranceCases')]
    public function testRefusesAnInvalidInsurance(string $insurer, string $policy): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Insurance($insurer, $policy);
    }
}
