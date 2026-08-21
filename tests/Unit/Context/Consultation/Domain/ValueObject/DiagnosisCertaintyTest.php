<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiagnosisCertaintyTest extends TestCase
{
    #[DataProvider('provideLabelCases')]
    public function testLabel(DiagnosisCertainty $certainty, string $expected): void
    {
        self::assertSame($expected, $certainty->label());
    }

    /**
     * @return iterable<string, array{DiagnosisCertainty, string}>
     */
    public static function provideLabelCases(): iterable
    {
        yield 'certain' => [DiagnosisCertainty::CERTAIN, 'Certain'];
        yield 'probable' => [DiagnosisCertainty::PROBABLE, 'Probable'];
        yield 'possible' => [DiagnosisCertainty::POSSIBLE, 'Possible'];
        yield 'excluded' => [DiagnosisCertainty::EXCLUDED, 'Exclu'];
    }

    #[DataProvider('provideShortLabelCases')]
    public function testShortLabel(DiagnosisCertainty $certainty, string $expected): void
    {
        self::assertSame($expected, $certainty->shortLabel());
    }

    /**
     * @return iterable<string, array{DiagnosisCertainty, string}>
     */
    public static function provideShortLabelCases(): iterable
    {
        yield 'certain' => [DiagnosisCertainty::CERTAIN, 'Cert.'];
        yield 'probable' => [DiagnosisCertainty::PROBABLE, 'Prob.'];
        yield 'possible' => [DiagnosisCertainty::POSSIBLE, 'Poss.'];
        yield 'excluded' => [DiagnosisCertainty::EXCLUDED, 'Exclu'];
    }

    public function testEveryCaseIsCovered(): void
    {
        self::assertCount(4, DiagnosisCertainty::cases());
    }
}
