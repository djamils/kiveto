<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\DiagnosisSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiagnosisSourceTest extends TestCase
{
    #[DataProvider('provideLabelCases')]
    public function testLabel(DiagnosisSource $source, string $expected): void
    {
        self::assertSame($expected, $source->label());
    }

    /**
     * @return iterable<string, array{DiagnosisSource, string}>
     */
    public static function provideLabelCases(): iterable
    {
        yield 'manual' => [DiagnosisSource::MANUAL, 'Saisi manuellement'];
        yield 'ai suggestion' => [DiagnosisSource::AI_SUGGESTION, 'Suggestion IA acceptée'];
    }

    public function testEveryCaseIsCovered(): void
    {
        self::assertCount(2, DiagnosisSource::cases());
    }
}
