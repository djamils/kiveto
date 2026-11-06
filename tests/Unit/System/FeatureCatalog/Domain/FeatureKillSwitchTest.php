<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Domain;

use App\System\FeatureCatalog\Domain\FeatureAudience;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureSettings;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use PHPUnit\Framework\TestCase;

/**
 * The cut off everywhere and the audience of a feature's settings.
 */
final class FeatureKillSwitchTest extends TestCase
{
    public function testAFeatureIsCutOffWithItsReasonThenRestored(): void
    {
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::ELECTRONIC_INVOICING, FeatureStatus::BETA, false, null);
        $at       = new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('UTC'));

        $settings->cut("  Faille chez la Plateforme Agréée\n", 'Djamil Soidiki', $at);

        $cut = $settings->cutOff();
        self::assertNotNull($cut);
        self::assertSame(['Faille chez la Plateforme Agréée', 'Djamil Soidiki', $at], [$cut->reason, $cut->byName, $cut->at]);
        self::assertSame($at, $settings->updatedAt());

        $later = $at->modify('+2 hours');
        $settings->restore($later);

        self::assertNull($settings->cutOff());
        self::assertSame($later, $settings->updatedAt());
    }

    public function testALongReasonIsCut(): void
    {
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, true, null);
        $settings->cut(str_repeat('é', 300), null, new \DateTimeImmutable());

        self::assertSame(255, mb_strlen((string) $settings->cutOff()?->reason));
    }

    public function testTheCutNeedsAReason(): void
    {
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, true, null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Indique pourquoi tu la coupes');

        $settings->cut('   ', null, new \DateTimeImmutable());
    }

    public function testAFeatureIsNotCutTwice(): void
    {
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, true, null);
        $settings->cut('Incident', null, new \DateTimeImmutable());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('déjà coupée partout');

        $settings->cut('Encore', null, new \DateTimeImmutable());
    }

    public function testAnUpcomingFeatureIsNotCut(): void
    {
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::HOSPITALISATION, FeatureStatus::UPCOMING, false, null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('« À venir » est déjà coupée partout');

        $settings->cut('Incident', null, new \DateTimeImmutable());
    }

    public function testOnlyACutFeatureIsRestored(): void
    {
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, true, null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('n’est pas coupée');

        $settings->restore(new \DateTimeImmutable());
    }

    public function testTheAudienceChangesWithTheSettings(): void
    {
        $settings = FeatureSettings::reconstitute(FeatureCatalogue::SMS_REMINDERS, FeatureStatus::AVAILABLE, true, null);
        $at       = new \DateTimeImmutable('2026-11-06 11:00:00');

        self::assertSame(['Réservée à : Toutes les cliniques → Cliniques payantes'], $settings->change(FeatureStatus::AVAILABLE, true, $at, FeatureAudience::PAID));
        self::assertSame(FeatureAudience::PAID, $settings->audience());
        self::assertSame([], $settings->change(FeatureStatus::AVAILABLE, true, $at), 'no audience given: unchanged');
        self::assertSame(FeatureAudience::PAID, $settings->audience());
    }
}
