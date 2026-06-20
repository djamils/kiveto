<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Adapter\Supplier\Simulation;

/**
 * Encapsulates a named simulation profile with its associated behaviour settings.
 *
 * Profiles control how the SimulatedSupplierAdapter behaves:
 *   - DEMO_FAST      — instant delivery, suitable for live demos
 *   - STAGING_REALISTIC — 1-hour delay, mimics a real supplier turnaround
 *   - DEV_INSTANT    — same as DEMO_FAST but semantically tagged for development
 */
final readonly class SimulationProfileConfig
{
    public const string DEMO_FAST         = 'DEMO_FAST';
    public const string STAGING_REALISTIC = 'STAGING_REALISTIC';
    public const string DEV_INSTANT       = 'DEV_INSTANT';

    private function __construct(
        public readonly string $profile,
        public readonly int $deliveryDelaySeconds,
    ) {
    }

    public static function demeFast(): self
    {
        return new self(self::DEMO_FAST, 0);
    }

    public static function stagingRealistic(): self
    {
        return new self(self::STAGING_REALISTIC, 3600);
    }

    public static function devInstant(): self
    {
        return new self(self::DEV_INSTANT, 0);
    }

    public static function fromString(string $profile): self
    {
        return match ($profile) {
            self::DEMO_FAST         => self::demeFast(),
            self::STAGING_REALISTIC => self::stagingRealistic(),
            self::DEV_INSTANT       => self::devInstant(),
            default                 => self::demeFast(), // Default to DEMO_FAST for unknown profiles
        };
    }
}
