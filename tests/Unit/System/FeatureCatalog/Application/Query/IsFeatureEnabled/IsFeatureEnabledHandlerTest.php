<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Application\Query\IsFeatureEnabled;

use App\System\FeatureCatalog\Application\Query\IsFeatureEnabled\IsFeatureEnabled;
use App\System\FeatureCatalog\Application\Query\IsFeatureEnabled\IsFeatureEnabledHandler;
use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\Tests\Unit\System\FeatureCatalog\Support\FakeClinicPlans;
use App\Tests\Unit\System\FeatureCatalog\Support\InMemoryFeatures;
use PHPUnit\Framework\TestCase;

final class IsFeatureEnabledHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000f6a1';
    private const string OTHER  = '01960000-0000-7000-8000-00000000f6a2';

    public function testTheModulesAskWhetherTheClinicHasTheirFeature(): void
    {
        $features = new InMemoryFeatures();
        $features->set(self::CLINIC, FeatureCatalogue::ONLINE_BOOKING, false);
        $features->set(self::CLINIC, FeatureCatalogue::SUPPORT_WRITE_MODE, true);
        $features->set(self::CLINIC, FeatureCatalogue::HOSPITALISATION, true);
        $handler = new IsFeatureEnabledHandler(new FeatureResolver($features, new FakeClinicPlans()));

        self::assertFalse($handler(new IsFeatureEnabled(self::CLINIC, FeatureCatalogue::ONLINE_BOOKING)), 'switched off');
        self::assertTrue($handler(new IsFeatureEnabled(self::OTHER, FeatureCatalogue::ONLINE_BOOKING)), 'another clinic keeps the legacy state');
        self::assertTrue($handler(new IsFeatureEnabled(self::CLINIC, FeatureCatalogue::SUPPORT_WRITE_MODE)), 'switched on');
        self::assertFalse($handler(new IsFeatureEnabled(self::OTHER, FeatureCatalogue::SUPPORT_WRITE_MODE)), 'legacy off');
        self::assertFalse($handler(new IsFeatureEnabled(self::CLINIC, FeatureCatalogue::HOSPITALISATION)), 'upcoming');
        self::assertFalse($handler(new IsFeatureEnabled(self::CLINIC, 'portal.teleconsultation')), 'unknown code');
    }
}
