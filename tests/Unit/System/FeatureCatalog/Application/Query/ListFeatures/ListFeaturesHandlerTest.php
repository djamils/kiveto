<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Application\Query\ListFeatures;

use App\System\FeatureCatalog\Application\Query\FeatureView;
use App\System\FeatureCatalog\Application\Query\ListFeatures\ListFeatures;
use App\System\FeatureCatalog\Application\Query\ListFeatures\ListFeaturesHandler;
use App\System\FeatureCatalog\Application\Service\FeatureStates;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureDefinition;
use App\System\FeatureCatalog\Domain\FeatureSettings;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use App\Tests\Unit\System\FeatureCatalog\Support\InMemoryFeatures;
use PHPUnit\Framework\TestCase;

final class ListFeaturesHandlerTest extends TestCase
{
    private const string A     = '01960000-0000-7000-8000-00000000f4a1';
    private const string B     = '01960000-0000-7000-8000-00000000f4a2';
    private const string C     = '01960000-0000-7000-8000-00000000f4a3';
    private const string OTHER = '01960000-0000-7000-8000-00000000f4a4';

    public function testEveryFeatureIsCountedOverTheClinicsGiven(): void
    {
        $features = new InMemoryFeatures();
        // A switched it off, B keeps the legacy state (on), C switched it on; a clinic not listed changed it last.
        $features->set(self::A, FeatureCatalogue::ONLINE_BOOKING, false, '2026-11-01 08:00:00');
        $features->set(self::C, FeatureCatalogue::ONLINE_BOOKING, true, '2026-11-03 08:00:00');
        $features->set(self::OTHER, FeatureCatalogue::ONLINE_BOOKING, false, '2026-11-05 08:00:00');
        // Settings saved after the clinics' last change.
        $features->set(self::A, FeatureCatalogue::ELECTRONIC_INVOICING, false, '2026-11-02 08:00:00');
        $features->saveSettings(FeatureSettings::reconstitute(FeatureCatalogue::ELECTRONIC_INVOICING, FeatureStatus::AVAILABLE, true, new \DateTimeImmutable('2026-11-04 08:00:00', new \DateTimeZone('UTC'))));
        // An upcoming feature is on nowhere, whatever a row says.
        $features->set(self::A, FeatureCatalogue::HOSPITALISATION, true, '2026-10-01 08:00:00');

        $views = [];
        foreach (new ListFeaturesHandler($features, new FeatureStates($features))(new ListFeatures([self::A, self::B, self::C])) as $view) {
            $views[$view->code] = $view;
        }

        self::assertSame(array_map(static fn (FeatureDefinition $definition): string => $definition->code, FeatureCatalogue::all()), array_keys($views));
        self::assertSame([2, '2026-11-05 08:00'], self::countAndLast($views[FeatureCatalogue::ONLINE_BOOKING]));
        self::assertSame([3, null], self::countAndLast($views[FeatureCatalogue::SMS_REMINDERS]), 'never set, never saved: legacy on, no date');
        self::assertSame([2, '2026-11-04 08:00'], self::countAndLast($views[FeatureCatalogue::ELECTRONIC_INVOICING]));
        self::assertSame([0, '2026-10-01 08:00'], self::countAndLast($views[FeatureCatalogue::HOSPITALISATION]));
        self::assertSame([0, null], self::countAndLast($views[FeatureCatalogue::SUPPORT_WRITE_MODE]), 'legacy off');

        $invoicing = $views[FeatureCatalogue::ELECTRONIC_INVOICING];
        self::assertSame(
            ['Facturation électronique', 'Émission via la Plateforme Agréée.', 'available', 'Disponible', true, false],
            [$invoicing->name, $invoicing->description, $invoicing->status, $invoicing->statusLabel, $invoicing->defaultOn, $invoicing->isUpcoming()],
        );
        self::assertStringStartsWith('Facturation : ', $invoicing->effect);

        $hospitalisation = $views[FeatureCatalogue::HOSPITALISATION];
        self::assertSame(['upcoming', 'À venir', false, true], [$hospitalisation->status, $hospitalisation->statusLabel, $hospitalisation->defaultOn, $hospitalisation->isUpcoming()]);
    }

    public function testNoClinicGivenCountsNone(): void
    {
        $views = new ListFeaturesHandler(new InMemoryFeatures(), new FeatureStates(new InMemoryFeatures()))(new ListFeatures([]));

        self::assertSame([0, 0, 0, 0, 0, 0], array_map(static fn (FeatureView $view): int => $view->enabledCount, $views));
    }

    /**
     * @return array{int, ?string}
     */
    private static function countAndLast(FeatureView $view): array
    {
        return [$view->enabledCount, $view->updatedAt?->format('Y-m-d H:i')];
    }
}
