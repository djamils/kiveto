<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Domain;

use App\System\FeatureCatalog\Domain\FeatureAudience;
use PHPUnit\Framework\TestCase;

final class FeatureAudienceTest extends TestCase
{
    public function testEveryClinicOrThoseOfOnePlan(): void
    {
        self::assertTrue(FeatureAudience::ALL->admits('paid'));
        self::assertTrue(FeatureAudience::ALL->admits(null));
        self::assertTrue(FeatureAudience::PAID->admits('paid'));
        self::assertFalse(FeatureAudience::PAID->admits('pilot'));
        self::assertFalse(FeatureAudience::PAID->admits(null), 'a clinic without an account is a pilot');
        self::assertTrue(FeatureAudience::PILOT->admits(null));
        self::assertFalse(FeatureAudience::PILOT->admits('paid'));
    }

    public function testLabels(): void
    {
        self::assertSame(
            ['Toutes les cliniques', 'Cliniques pilotes', 'Cliniques payantes'],
            array_map(static fn (FeatureAudience $a): string => $a->label(), FeatureAudience::cases()),
        );
    }
}
