<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlanActionKindTest extends TestCase
{
    #[DataProvider('provideLabelCases')]
    public function testLabel(PlanActionKind $kind, string $expected): void
    {
        self::assertSame($expected, $kind->label());
    }

    /**
     * @return iterable<string, array{PlanActionKind, string}>
     */
    public static function provideLabelCases(): iterable
    {
        yield 'performed act' => [PlanActionKind::PERFORMED_ACT, 'Actes'];
        yield 'medication prescription' => [PlanActionKind::MEDICATION_PRESCRIPTION, 'Médicaments'];
        yield 'follow up appointment' => [PlanActionKind::FOLLOW_UP_APPOINTMENT, 'RDV'];
        yield 'advice' => [PlanActionKind::ADVICE, 'Conseils'];
        yield 'other' => [PlanActionKind::OTHER, 'Autre'];
    }

    #[DataProvider('provideSingularLabelCases')]
    public function testSingularLabel(PlanActionKind $kind, string $expected): void
    {
        self::assertSame($expected, $kind->singularLabel());
    }

    /**
     * @return iterable<string, array{PlanActionKind, string}>
     */
    public static function provideSingularLabelCases(): iterable
    {
        yield 'performed act' => [PlanActionKind::PERFORMED_ACT, 'Acte'];
        yield 'medication prescription' => [PlanActionKind::MEDICATION_PRESCRIPTION, 'Médicament'];
        yield 'follow up appointment' => [PlanActionKind::FOLLOW_UP_APPOINTMENT, 'RDV'];
        yield 'advice' => [PlanActionKind::ADVICE, 'Conseil'];
        yield 'other' => [PlanActionKind::OTHER, 'Autre'];
    }

    #[DataProvider('provideIsBillableCases')]
    public function testIsBillable(PlanActionKind $kind, bool $expected): void
    {
        self::assertSame($expected, $kind->isBillable());
    }

    /**
     * @return iterable<string, array{PlanActionKind, bool}>
     */
    public static function provideIsBillableCases(): iterable
    {
        yield 'performed act' => [PlanActionKind::PERFORMED_ACT, true];
        yield 'medication prescription' => [PlanActionKind::MEDICATION_PRESCRIPTION, false];
        yield 'follow up appointment' => [PlanActionKind::FOLLOW_UP_APPOINTMENT, false];
        yield 'advice' => [PlanActionKind::ADVICE, false];
        yield 'other' => [PlanActionKind::OTHER, false];
    }

    public function testEveryCaseIsCovered(): void
    {
        self::assertCount(5, PlanActionKind::cases());
    }
}
