<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Adapter\Supplier\Simulation;

use App\Context\Procurement\Infrastructure\Adapter\Supplier\Simulation\SimulationProfileConfig;
use PHPUnit\Framework\TestCase;

final class SimulationProfileConfigTest extends TestCase
{
    public function testDemoFastFactory(): void
    {
        $config = SimulationProfileConfig::demeFast();

        self::assertSame(SimulationProfileConfig::DEMO_FAST, $config->profile);
        self::assertSame(0, $config->deliveryDelaySeconds);
    }

    public function testStagingRealisticFactory(): void
    {
        $config = SimulationProfileConfig::stagingRealistic();

        self::assertSame(SimulationProfileConfig::STAGING_REALISTIC, $config->profile);
        self::assertSame(3600, $config->deliveryDelaySeconds);
    }

    public function testDevInstantFactory(): void
    {
        $config = SimulationProfileConfig::devInstant();

        self::assertSame(SimulationProfileConfig::DEV_INSTANT, $config->profile);
        self::assertSame(0, $config->deliveryDelaySeconds);
    }

    public function testFromStringResolvesDemoFast(): void
    {
        $config = SimulationProfileConfig::fromString(SimulationProfileConfig::DEMO_FAST);

        self::assertSame(SimulationProfileConfig::DEMO_FAST, $config->profile);
        self::assertSame(0, $config->deliveryDelaySeconds);
    }

    public function testFromStringResolvesStagingRealistic(): void
    {
        $config = SimulationProfileConfig::fromString(SimulationProfileConfig::STAGING_REALISTIC);

        self::assertSame(SimulationProfileConfig::STAGING_REALISTIC, $config->profile);
        self::assertSame(3600, $config->deliveryDelaySeconds);
    }

    public function testFromStringResolvesDevInstant(): void
    {
        $config = SimulationProfileConfig::fromString(SimulationProfileConfig::DEV_INSTANT);

        self::assertSame(SimulationProfileConfig::DEV_INSTANT, $config->profile);
    }

    public function testFromStringFallsBackToDemoFastForUnknownProfile(): void
    {
        $config = SimulationProfileConfig::fromString('UNKNOWN_PROFILE');

        self::assertSame(SimulationProfileConfig::DEMO_FAST, $config->profile);
    }
}
