<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Application\Command\SetClinicFeature;

use App\System\FeatureCatalog\Application\Command\SetClinicFeature\SetClinicFeature;
use App\System\FeatureCatalog\Application\Command\SetClinicFeature\SetClinicFeatureHandler;
use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\UnknownFeatureException;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\FeatureCatalog\Support\FakeClinicPlans;
use App\Tests\Unit\System\FeatureCatalog\Support\InMemoryFeatures;
use PHPUnit\Framework\TestCase;

final class SetClinicFeatureHandlerTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000f2a1';
    private const string ACTOR  = '01960000-0000-7000-8000-00000000f2b1';

    private InMemoryFeatures $features;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->features = new InMemoryFeatures();
        $this->clock    = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));
    }

    public function testSwitchingOffALegacyFeatureChangesTheStateInForce(): void
    {
        self::assertTrue($this->set(FeatureCatalogue::ELECTRONIC_INVOICING, false));

        $saved = $this->features->clinicFeature(self::CLINIC, FeatureCatalogue::ELECTRONIC_INVOICING);
        self::assertNotNull($saved);
        self::assertSame([self::CLINIC, FeatureCatalogue::ELECTRONIC_INVOICING, false, $this->clock->now(), self::ACTOR, 'Djamil Soidiki'], [
            $saved->clinicId, $saved->code, $saved->enabled, $saved->changedAt, $saved->changedById, $saved->changedByName,
        ]);
    }

    public function testSwitchingBackOnChangesItAgain(): void
    {
        $this->features->set(self::CLINIC, FeatureCatalogue::SMS_REMINDERS, false);

        self::assertTrue($this->set(FeatureCatalogue::SMS_REMINDERS, true));
        self::assertTrue($this->features->clinicFeature(self::CLINIC, FeatureCatalogue::SMS_REMINDERS)?->enabled);
    }

    public function testTheStateAlreadyStoredChangesNothing(): void
    {
        $this->features->set(self::CLINIC, FeatureCatalogue::SUPPORT_WRITE_MODE, true);

        self::assertFalse($this->set(FeatureCatalogue::SUPPORT_WRITE_MODE, true));
        self::assertSame([], $this->features->savedClinicFeatures);
    }

    /**
     * A clinic that kept the legacy state is given its row, the state in
     * force staying the same.
     */
    public function testConfirmingTheLegacyStateWritesTheRowWithoutChange(): void
    {
        self::assertFalse($this->set(FeatureCatalogue::ONLINE_BOOKING, true));

        self::assertCount(1, $this->features->savedClinicFeatures);
        self::assertTrue($this->features->savedClinicFeatures[0]->enabled);
    }

    public function testAnUpcomingFeatureIsNotSwitchedOn(): void
    {
        try {
            $this->set(FeatureCatalogue::HOSPITALISATION, true);
            self::fail('An upcoming feature was expected to be refused.');
        } catch (\DomainException $e) {
            self::assertSame('Une fonctionnalité « À venir » ne s’active pas : son module n’existe pas encore.', $e->getMessage());
        }

        self::assertSame([], $this->features->savedClinicFeatures);
    }

    public function testAnUnknownFeatureIsRefused(): void
    {
        $this->expectException(UnknownFeatureException::class);

        $this->set('portal.teleconsultation', true);
    }

    private function set(string $code, bool $enabled): bool
    {
        return new SetClinicFeatureHandler($this->features, new FeatureResolver($this->features, new FakeClinicPlans()), $this->clock)(
            new SetClinicFeature(self::CLINIC, $code, $enabled, self::ACTOR, 'Djamil Soidiki'),
        );
    }
}
