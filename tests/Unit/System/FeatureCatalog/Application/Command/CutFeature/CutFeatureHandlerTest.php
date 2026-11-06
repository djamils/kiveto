<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Application\Command\CutFeature;

use App\System\FeatureCatalog\Application\Command\CutFeature\CutFeature;
use App\System\FeatureCatalog\Application\Command\CutFeature\CutFeatureHandler;
use App\System\FeatureCatalog\Application\Command\RestoreFeature\RestoreFeature;
use App\System\FeatureCatalog\Application\Command\RestoreFeature\RestoreFeatureHandler;
use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\FeatureCatalog\Support\FakeClinicPlans;
use App\Tests\Unit\System\FeatureCatalog\Support\InMemoryFeatures;
use PHPUnit\Framework\TestCase;

final class CutFeatureHandlerTest extends TestCase
{
    public function testAFeatureIsCutOffEverywhereThenRestored(): void
    {
        $features = new InMemoryFeatures();
        $resolver = new FeatureResolver($features, new FakeClinicPlans());
        $clock    = new FrozenClock(new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('UTC')));

        new CutFeatureHandler($features, $resolver, $clock)(new CutFeature(FeatureCatalogue::ONLINE_BOOKING, 'Réservations en double', 'Djamil Soidiki'));

        $saved = $features->settings(FeatureCatalogue::ONLINE_BOOKING);
        self::assertSame(['Réservations en double', 'Djamil Soidiki'], [$saved?->cutOff()?->reason, $saved?->cutOff()?->byName]);

        new RestoreFeatureHandler($features, $resolver, $clock)(new RestoreFeature(FeatureCatalogue::ONLINE_BOOKING));

        self::assertNull($features->settings(FeatureCatalogue::ONLINE_BOOKING)?->cutOff());
    }
}
