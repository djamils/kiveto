<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

/**
 * Additional vitals recorded alongside the weight/temperature pair held by {@see Vitals}.
 *
 * Reference data (unit, acceptance bounds, display range) lives here so the
 * server stays the single source for both validation and the cockpit hydration
 * payload — no parallel configuration file.
 */
enum VitalType: string
{
    case HEART_RATE            = 'HEART_RATE';
    case RESPIRATORY_RATE      = 'RESPIRATORY_RATE';
    case BODY_CONDITION_SCORE  = 'BODY_CONDITION_SCORE';
    case PAIN_SCORE            = 'PAIN_SCORE';
    case CAPILLARY_REFILL_TIME = 'CAPILLARY_REFILL_TIME';
    case MUCOUS_MEMBRANES      = 'MUCOUS_MEMBRANES';
    case BLOOD_PRESSURE        = 'BLOOD_PRESSURE';
    case GLYCEMIA              = 'GLYCEMIA';

    public function label(): string
    {
        return match ($this) {
            self::HEART_RATE            => 'Fréq. cardiaque',
            self::RESPIRATORY_RATE      => 'Fréq. respiratoire',
            self::BODY_CONDITION_SCORE  => 'Score corporel',
            self::PAIN_SCORE            => 'Score douleur',
            self::CAPILLARY_REFILL_TIME => 'TRC',
            self::MUCOUS_MEMBRANES      => 'Muqueuses',
            self::BLOOD_PRESSURE        => 'Tension artérielle',
            self::GLYCEMIA              => 'Glycémie',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::HEART_RATE            => 'bpm',
            self::RESPIRATORY_RATE      => 'rpm',
            self::BODY_CONDITION_SCORE  => '/9',
            self::PAIN_SCORE            => '/4',
            self::CAPILLARY_REFILL_TIME => 's',
            self::MUCOUS_MEMBRANES      => '',
            self::BLOOD_PRESSURE        => 'mmHg',
            self::GLYCEMIA              => 'mmol/L',
        };
    }

    /**
     * Lower acceptance bound, or null when the value is free text.
     */
    public function min(): ?float
    {
        return match ($this) {
            self::HEART_RATE            => 10.0,
            self::RESPIRATORY_RATE      => 2.0,
            self::BODY_CONDITION_SCORE  => 1.0,
            self::PAIN_SCORE            => 0.0,
            self::CAPILLARY_REFILL_TIME => 0.0,
            self::GLYCEMIA              => 0.0,
            self::MUCOUS_MEMBRANES, self::BLOOD_PRESSURE => null,
        };
    }

    /**
     * Upper acceptance bound, or null when the value is free text.
     */
    public function max(): ?float
    {
        return match ($this) {
            self::HEART_RATE            => 400.0,
            self::RESPIRATORY_RATE      => 200.0,
            self::BODY_CONDITION_SCORE  => 9.0,
            self::PAIN_SCORE            => 4.0,
            self::CAPILLARY_REFILL_TIME => 10.0,
            self::GLYCEMIA              => 60.0,
            self::MUCOUS_MEMBRANES, self::BLOOD_PRESSURE => null,
        };
    }

    public function isNumeric(): bool
    {
        return null !== $this->min();
    }

    /**
     * Normal range displayed as a hint next to the input.
     */
    public function referenceRange(): string
    {
        return match ($this) {
            self::HEART_RATE            => '70–120',
            self::RESPIRATORY_RATE      => '15–30',
            self::BODY_CONDITION_SCORE  => 'Idéal 4–5',
            self::PAIN_SCORE            => '0 = aucune',
            self::CAPILLARY_REFILL_TIME => '< 2 s',
            self::MUCOUS_MEMBRANES      => 'Roses',
            self::BLOOD_PRESSURE        => '120/80',
            self::GLYCEMIA              => '4–7',
        };
    }

    public function defaultValue(): string
    {
        return match ($this) {
            self::HEART_RATE            => '90',
            self::RESPIRATORY_RATE      => '20',
            self::BODY_CONDITION_SCORE  => '5',
            self::PAIN_SCORE            => '0',
            self::CAPILLARY_REFILL_TIME => '1.5',
            self::MUCOUS_MEMBRANES      => 'Roses',
            self::BLOOD_PRESSURE        => '125/80',
            self::GLYCEMIA              => '5.2',
        };
    }
}
