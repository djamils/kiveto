<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Domain;

use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureSettings;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeatureSettingsTest extends TestCase
{
    public function testAFeatureNeverSavedReadsItsInitialSettings(): void
    {
        $settings = FeatureSettings::initial(FeatureCatalogue::get(FeatureCatalogue::ELECTRONIC_INVOICING));

        self::assertSame(['billing.electronic_invoicing', FeatureStatus::BETA, false, null, false], [
            $settings->code(), $settings->status(), $settings->isDefaultOn(), $settings->updatedAt(), $settings->isUpcoming(),
        ]);
        self::assertTrue(FeatureSettings::initial(FeatureCatalogue::get(FeatureCatalogue::HOSPITALISATION))->isUpcoming());
    }

    public function testAChangeAnswersItsLinesForTheHistory(): void
    {
        $before   = new \DateTimeImmutable('2026-10-01 08:00:00', new \DateTimeZone('UTC'));
        $at       = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::ELECTRONIC_INVOICING, FeatureStatus::BETA, false, $before);

        self::assertSame(['Statut : Bêta → Disponible', 'Activée par défaut pour les nouvelles cliniques'], $settings->change(FeatureStatus::AVAILABLE, true, $at));
        self::assertSame([FeatureStatus::AVAILABLE, true, $at], [$settings->status(), $settings->isDefaultOn(), $settings->updatedAt()]);

        $later = $at->modify('+1 day');
        self::assertSame(['Plus activée par défaut pour les nouvelles cliniques'], $settings->change(FeatureStatus::AVAILABLE, false, $later));
        self::assertSame([FeatureStatus::AVAILABLE, false, $later], [$settings->status(), $settings->isDefaultOn(), $settings->updatedAt()]);

        self::assertSame(['Statut : Disponible → Interne'], $settings->change(FeatureStatus::INTERNAL, false, $later->modify('+1 day')));
    }

    public function testNoChangeLeavesTheSettingsUntouched(): void
    {
        $before   = new \DateTimeImmutable('2026-10-01 08:00:00', new \DateTimeZone('UTC'));
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, true, $before);

        self::assertSame([], $settings->change(FeatureStatus::AVAILABLE, true, new \DateTimeImmutable('2026-11-06 09:00:00')));
        self::assertSame($before, $settings->updatedAt());
    }

    #[DataProvider('provideAnUpcomingFeatureIsNotSetCases')]
    public function testAnUpcomingFeatureIsNotSet(FeatureStatus $current, FeatureStatus $requested): void
    {
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::HOSPITALISATION, $current, false, null);

        try {
            $settings->change($requested, true, new \DateTimeImmutable());
            self::fail('An upcoming feature was expected to be refused.');
        } catch (\DomainException $e) {
            self::assertSame('Une fonctionnalité « À venir » ne se règle pas : son module n’existe pas encore.', $e->getMessage());
        }

        self::assertSame([$current, false, null], [$settings->status(), $settings->isDefaultOn(), $settings->updatedAt()]);
    }

    /**
     * @return iterable<string, array{FeatureStatus, FeatureStatus}>
     */
    public static function provideAnUpcomingFeatureIsNotSetCases(): iterable
    {
        yield 'an upcoming feature' => [FeatureStatus::UPCOMING, FeatureStatus::AVAILABLE];

        yield 'set to upcoming' => [FeatureStatus::BETA, FeatureStatus::UPCOMING];
    }
}
