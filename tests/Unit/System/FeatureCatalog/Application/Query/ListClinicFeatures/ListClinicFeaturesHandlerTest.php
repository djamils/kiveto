<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Application\Query\ListClinicFeatures;

use App\System\FeatureCatalog\Application\Query\ClinicFeatureView;
use App\System\FeatureCatalog\Application\Query\ListClinicFeatures\ListClinicFeatures;
use App\System\FeatureCatalog\Application\Query\ListClinicFeatures\ListClinicFeaturesHandler;
use App\System\FeatureCatalog\Application\Service\FeatureStates;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureDefinition;
use App\Tests\Unit\System\FeatureCatalog\Support\InMemoryFeatures;
use PHPUnit\Framework\TestCase;

final class ListClinicFeaturesHandlerTest extends TestCase
{
    private const string A     = '01960000-0000-7000-8000-00000000f5a1';
    private const string B     = '01960000-0000-7000-8000-00000000f5a2';
    private const string OTHER = '01960000-0000-7000-8000-00000000f5a3';

    public function testEachFeatureOfEachClinicIsGivenWithItsLastChange(): void
    {
        $features = new InMemoryFeatures();
        $features->set(self::A, FeatureCatalogue::ONLINE_BOOKING, false, '2026-11-01 08:00:00', 'Djamil');
        $features->set(self::OTHER, FeatureCatalogue::ONLINE_BOOKING, true);
        $features->set(self::B, FeatureCatalogue::HOSPITALISATION, true);

        $answer = new ListClinicFeaturesHandler($features, new FeatureStates($features))(new ListClinicFeatures([self::A, self::B]));

        self::assertSame(array_map(static fn (FeatureDefinition $definition): string => $definition->code, FeatureCatalogue::all()), array_keys($answer));
        foreach ($answer as $byClinic) {
            self::assertSame([self::A, self::B], array_keys($byClinic), 'the clinics asked about only');
        }

        self::assertSame([self::A, FeatureCatalogue::ONLINE_BOOKING, false, '2026-11-01 08:00', 'Djamil'], self::fields($answer[FeatureCatalogue::ONLINE_BOOKING][self::A]));
        self::assertSame([self::B, FeatureCatalogue::ONLINE_BOOKING, true, null, null], self::fields($answer[FeatureCatalogue::ONLINE_BOOKING][self::B]), 'the legacy state');
        self::assertFalse($answer[FeatureCatalogue::HOSPITALISATION][self::B]->enabled, 'an upcoming feature is off');
        self::assertFalse($answer[FeatureCatalogue::SUPPORT_WRITE_MODE][self::A]->enabled);
    }

    public function testNoClinicGivenListsEveryFeatureEmpty(): void
    {
        $answer = new ListClinicFeaturesHandler(new InMemoryFeatures(), new FeatureStates(new InMemoryFeatures()))(new ListClinicFeatures([]));

        self::assertSame(array_fill_keys(array_map(static fn (FeatureDefinition $definition): string => $definition->code, FeatureCatalogue::all()), []), $answer);
    }

    /**
     * @return array{string, string, bool, ?string, ?string}
     */
    private static function fields(ClinicFeatureView $view): array
    {
        return [$view->clinicId, $view->code, $view->enabled, $view->changedAt?->format('Y-m-d H:i'), $view->changedByName];
    }
}
