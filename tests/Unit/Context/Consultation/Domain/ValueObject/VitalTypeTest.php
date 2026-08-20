<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\VitalType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VitalTypeTest extends TestCase
{
    #[DataProvider('provideLabelCases')]
    public function testLabel(VitalType $type, string $expected): void
    {
        self::assertSame($expected, $type->label());
    }

    /**
     * @return iterable<string, array{VitalType, string}>
     */
    public static function provideLabelCases(): iterable
    {
        yield 'heart rate' => [VitalType::HEART_RATE, 'Fréq. cardiaque'];
        yield 'respiratory rate' => [VitalType::RESPIRATORY_RATE, 'Fréq. respiratoire'];
        yield 'body condition score' => [VitalType::BODY_CONDITION_SCORE, 'Score corporel'];
        yield 'pain score' => [VitalType::PAIN_SCORE, 'Score douleur'];
        yield 'capillary refill time' => [VitalType::CAPILLARY_REFILL_TIME, 'TRC'];
        yield 'mucous membranes' => [VitalType::MUCOUS_MEMBRANES, 'Muqueuses'];
        yield 'blood pressure' => [VitalType::BLOOD_PRESSURE, 'Tension artérielle'];
        yield 'glycemia' => [VitalType::GLYCEMIA, 'Glycémie'];
    }

    #[DataProvider('provideUnitCases')]
    public function testUnit(VitalType $type, string $expected): void
    {
        self::assertSame($expected, $type->unit());
    }

    /**
     * @return iterable<string, array{VitalType, string}>
     */
    public static function provideUnitCases(): iterable
    {
        yield 'heart rate' => [VitalType::HEART_RATE, 'bpm'];
        yield 'respiratory rate' => [VitalType::RESPIRATORY_RATE, 'rpm'];
        yield 'body condition score' => [VitalType::BODY_CONDITION_SCORE, '/9'];
        yield 'pain score' => [VitalType::PAIN_SCORE, '/4'];
        yield 'capillary refill time' => [VitalType::CAPILLARY_REFILL_TIME, 's'];
        yield 'mucous membranes' => [VitalType::MUCOUS_MEMBRANES, ''];
        yield 'blood pressure' => [VitalType::BLOOD_PRESSURE, 'mmHg'];
        yield 'glycemia' => [VitalType::GLYCEMIA, 'mmol/L'];
    }

    #[DataProvider('provideMinCases')]
    public function testMin(VitalType $type, ?float $expected): void
    {
        self::assertSame($expected, $type->min());
    }

    /**
     * @return iterable<string, array{VitalType, float|null}>
     */
    public static function provideMinCases(): iterable
    {
        yield 'heart rate' => [VitalType::HEART_RATE, 10.0];
        yield 'respiratory rate' => [VitalType::RESPIRATORY_RATE, 2.0];
        yield 'body condition score' => [VitalType::BODY_CONDITION_SCORE, 1.0];
        yield 'pain score' => [VitalType::PAIN_SCORE, 0.0];
        yield 'capillary refill time' => [VitalType::CAPILLARY_REFILL_TIME, 0.0];
        yield 'glycemia' => [VitalType::GLYCEMIA, 0.0];
        yield 'mucous membranes' => [VitalType::MUCOUS_MEMBRANES, null];
        yield 'blood pressure' => [VitalType::BLOOD_PRESSURE, null];
    }

    #[DataProvider('provideMaxCases')]
    public function testMax(VitalType $type, ?float $expected): void
    {
        self::assertSame($expected, $type->max());
    }

    /**
     * @return iterable<string, array{VitalType, float|null}>
     */
    public static function provideMaxCases(): iterable
    {
        yield 'heart rate' => [VitalType::HEART_RATE, 400.0];
        yield 'respiratory rate' => [VitalType::RESPIRATORY_RATE, 200.0];
        yield 'body condition score' => [VitalType::BODY_CONDITION_SCORE, 9.0];
        yield 'pain score' => [VitalType::PAIN_SCORE, 4.0];
        yield 'capillary refill time' => [VitalType::CAPILLARY_REFILL_TIME, 10.0];
        yield 'glycemia' => [VitalType::GLYCEMIA, 60.0];
        yield 'mucous membranes' => [VitalType::MUCOUS_MEMBRANES, null];
        yield 'blood pressure' => [VitalType::BLOOD_PRESSURE, null];
    }

    #[DataProvider('provideIsNumericCases')]
    public function testIsNumeric(VitalType $type, bool $expected): void
    {
        self::assertSame($expected, $type->isNumeric());
    }

    /**
     * @return iterable<string, array{VitalType, bool}>
     */
    public static function provideIsNumericCases(): iterable
    {
        yield 'heart rate' => [VitalType::HEART_RATE, true];
        yield 'respiratory rate' => [VitalType::RESPIRATORY_RATE, true];
        yield 'body condition score' => [VitalType::BODY_CONDITION_SCORE, true];
        yield 'pain score' => [VitalType::PAIN_SCORE, true];
        yield 'capillary refill time' => [VitalType::CAPILLARY_REFILL_TIME, true];
        yield 'glycemia' => [VitalType::GLYCEMIA, true];
        yield 'mucous membranes' => [VitalType::MUCOUS_MEMBRANES, false];
        yield 'blood pressure' => [VitalType::BLOOD_PRESSURE, false];
    }

    #[DataProvider('provideReferenceRangeCases')]
    public function testReferenceRange(VitalType $type, string $expected): void
    {
        self::assertSame($expected, $type->referenceRange());
    }

    /**
     * @return iterable<string, array{VitalType, string}>
     */
    public static function provideReferenceRangeCases(): iterable
    {
        yield 'heart rate' => [VitalType::HEART_RATE, '70–120'];
        yield 'respiratory rate' => [VitalType::RESPIRATORY_RATE, '15–30'];
        yield 'body condition score' => [VitalType::BODY_CONDITION_SCORE, 'Idéal 4–5'];
        yield 'pain score' => [VitalType::PAIN_SCORE, '0 = aucune'];
        yield 'capillary refill time' => [VitalType::CAPILLARY_REFILL_TIME, '< 2 s'];
        yield 'mucous membranes' => [VitalType::MUCOUS_MEMBRANES, 'Roses'];
        yield 'blood pressure' => [VitalType::BLOOD_PRESSURE, '120/80'];
        yield 'glycemia' => [VitalType::GLYCEMIA, '4–7'];
    }

    #[DataProvider('provideDefaultValueCases')]
    public function testDefaultValue(VitalType $type, string $expected): void
    {
        self::assertSame($expected, $type->defaultValue());
    }

    /**
     * @return iterable<string, array{VitalType, string}>
     */
    public static function provideDefaultValueCases(): iterable
    {
        yield 'heart rate' => [VitalType::HEART_RATE, '90'];
        yield 'respiratory rate' => [VitalType::RESPIRATORY_RATE, '20'];
        yield 'body condition score' => [VitalType::BODY_CONDITION_SCORE, '5'];
        yield 'pain score' => [VitalType::PAIN_SCORE, '0'];
        yield 'capillary refill time' => [VitalType::CAPILLARY_REFILL_TIME, '1.5'];
        yield 'mucous membranes' => [VitalType::MUCOUS_MEMBRANES, 'Roses'];
        yield 'blood pressure' => [VitalType::BLOOD_PRESSURE, '125/80'];
        yield 'glycemia' => [VitalType::GLYCEMIA, '5.2'];
    }

    /**
     * The default value must itself pass the acceptance bounds of its type.
     */
    #[DataProvider('provideDefaultValueIsWithinBoundsCases')]
    public function testDefaultValueIsWithinBounds(VitalType $type): void
    {
        $min = $type->min();
        $max = $type->max();

        if (null === $min || null === $max) {
            self::assertFalse($type->isNumeric());

            return;
        }

        $default = (float) $type->defaultValue();

        self::assertGreaterThanOrEqual($min, $default);
        self::assertLessThanOrEqual($max, $default);
    }

    /**
     * @return iterable<string, array{VitalType}>
     */
    public static function provideDefaultValueIsWithinBoundsCases(): iterable
    {
        foreach (VitalType::cases() as $type) {
            yield $type->value => [$type];
        }
    }
}
