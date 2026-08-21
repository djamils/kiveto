<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\ExamStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExamStatusTest extends TestCase
{
    #[DataProvider('provideLabelCases')]
    public function testLabel(ExamStatus $status, string $expected): void
    {
        self::assertSame($expected, $status->label());
    }

    /**
     * @return iterable<string, array{ExamStatus, string}>
     */
    public static function provideLabelCases(): iterable
    {
        yield 'normal' => [ExamStatus::NORMAL, 'RAS'];
        yield 'anomaly' => [ExamStatus::ANOMALY, 'Anomalie'];
        yield 'untested' => [ExamStatus::UNTESTED, 'Non testé'];
    }

    public function testEveryCaseIsCovered(): void
    {
        self::assertCount(3, ExamStatus::cases());
    }
}
