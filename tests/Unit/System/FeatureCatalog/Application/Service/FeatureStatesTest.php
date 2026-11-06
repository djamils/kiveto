<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Application\Service;

use App\System\FeatureCatalog\Application\Service\FeatureStates;
use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureSettings;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use App\Tests\Unit\System\FeatureCatalog\Support\InMemoryFeatures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeatureStatesTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000f1a1';

    public function testTheSavedSettingsAreInForce(): void
    {
        $features = new InMemoryFeatures();
        $saved    = FeatureSettings::reconstitute(FeatureCatalogue::ELECTRONIC_INVOICING, FeatureStatus::AVAILABLE, true, new \DateTimeImmutable());
        $features->saveSettings($saved);

        self::assertSame($saved, new FeatureStates($features)->settings(FeatureCatalogue::get(FeatureCatalogue::ELECTRONIC_INVOICING)));
    }

    public function testAFeatureNeverSavedHasItsInitialSettings(): void
    {
        $settings = new FeatureStates(new InMemoryFeatures())->settings(FeatureCatalogue::get(FeatureCatalogue::ELECTRONIC_INVOICING));

        self::assertSame([FeatureStatus::BETA, false, null], [$settings->status(), $settings->isDefaultOn(), $settings->updatedAt()]);
    }

    #[DataProvider('provideWhatIsInForceForAClinicCases')]
    public function testWhatIsInForceForAClinic(string $code, FeatureStatus $status, ?bool $row, bool $on): void
    {
        $settings = FeatureSettings::reconstitute($code, $status, false, null);
        $clinic   = null !== $row ? new ClinicFeature(self::CLINIC, $code, $row, new \DateTimeImmutable(), null, null) : null;

        self::assertSame($on, FeatureStates::isOn(FeatureCatalogue::get($code), $settings, $clinic));
    }

    /**
     * @return iterable<string, array{string, FeatureStatus, ?bool, bool}>
     */
    public static function provideWhatIsInForceForAClinicCases(): iterable
    {
        yield 'switched on' => [FeatureCatalogue::SUPPORT_WRITE_MODE, FeatureStatus::INTERNAL, true, true];

        yield 'switched off' => [FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, false, false];

        yield 'never set, legacy on' => [FeatureCatalogue::ELECTRONIC_INVOICING, FeatureStatus::BETA, null, true];

        yield 'never set, legacy off' => [FeatureCatalogue::SUPPORT_WRITE_MODE, FeatureStatus::INTERNAL, null, false];

        yield 'upcoming, even switched on' => [FeatureCatalogue::HOSPITALISATION, FeatureStatus::UPCOMING, true, false];

        yield 'set back to upcoming' => [FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::UPCOMING, null, false];
    }
}
