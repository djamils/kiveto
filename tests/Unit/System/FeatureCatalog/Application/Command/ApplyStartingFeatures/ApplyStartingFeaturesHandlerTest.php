<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Application\Command\ApplyStartingFeatures;

use App\System\FeatureCatalog\Application\Command\ApplyStartingFeatures\ApplyStartingFeatures;
use App\System\FeatureCatalog\Application\Command\ApplyStartingFeatures\ApplyStartingFeaturesHandler;
use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureSettings;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\FeatureCatalog\Support\FakeClinicPlans;
use App\Tests\Unit\System\FeatureCatalog\Support\InMemoryFeatures;
use PHPUnit\Framework\TestCase;

final class ApplyStartingFeaturesHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000f3a1';
    private const string ACTOR  = '01960000-0000-7000-8000-00000000f3b1';

    /**
     * Every feature that exists is set, on when ticked; a ticked upcoming
     * feature is left aside.
     */
    public function testANewClinicStartsWithTheTickedFeatures(): void
    {
        $features = new InMemoryFeatures();
        $now      = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));

        $on = new ApplyStartingFeaturesHandler($features, new FeatureResolver($features, new FakeClinicPlans()), new FrozenClock($now))(new ApplyStartingFeatures(
            self::CLINIC,
            [FeatureCatalogue::ONLINE_BOOKING, FeatureCatalogue::HOSPITALISATION, FeatureCatalogue::SUPPORT_WRITE_MODE],
            self::ACTOR,
            'Djamil Soidiki',
        ));

        self::assertSame([FeatureCatalogue::ONLINE_BOOKING, FeatureCatalogue::SUPPORT_WRITE_MODE], $on);
        self::assertSame([
            [FeatureCatalogue::ONLINE_BOOKING, true],
            [FeatureCatalogue::SMS_REMINDERS, false],
            [FeatureCatalogue::ELECTRONIC_INVOICING, false],
            [FeatureCatalogue::SUPPORT_WRITE_MODE, true],
        ], array_map(static fn (ClinicFeature $feature): array => [$feature->code, $feature->enabled], $features->savedClinicFeatures));

        $first = $features->savedClinicFeatures[0];
        self::assertSame([self::CLINIC, $now, self::ACTOR, 'Djamil Soidiki'], [$first->clinicId, $first->changedAt, $first->changedById, $first->changedByName]);
    }

    public function testAFeatureSetBackToUpcomingIsSkippedToo(): void
    {
        $features = new InMemoryFeatures();
        $features->saveSettings(FeatureSettings::reconstitute(FeatureCatalogue::SMS_REMINDERS, FeatureStatus::UPCOMING, false, null));

        $on = new ApplyStartingFeaturesHandler($features, new FeatureResolver($features, new FakeClinicPlans()), new FrozenClock(new \DateTimeImmutable()))(
            new ApplyStartingFeatures(self::CLINIC, [FeatureCatalogue::SMS_REMINDERS], null, null),
        );

        self::assertSame([], $on);
        self::assertSame(
            [FeatureCatalogue::ONLINE_BOOKING, FeatureCatalogue::ELECTRONIC_INVOICING, FeatureCatalogue::SUPPORT_WRITE_MODE],
            array_map(static fn (ClinicFeature $feature): string => $feature->code, $features->savedClinicFeatures),
        );
        self::assertNull($features->savedClinicFeatures[0]->changedByName);
    }
}
