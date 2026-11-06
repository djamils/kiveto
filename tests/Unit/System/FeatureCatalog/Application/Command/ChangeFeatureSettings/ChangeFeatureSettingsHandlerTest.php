<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Application\Command\ChangeFeatureSettings;

use App\System\FeatureCatalog\Application\Command\ChangeFeatureSettings\ChangeFeatureSettings;
use App\System\FeatureCatalog\Application\Command\ChangeFeatureSettings\ChangeFeatureSettingsHandler;
use App\System\FeatureCatalog\Application\Service\FeatureStates;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use App\System\FeatureCatalog\Domain\UnknownFeatureException;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\FeatureCatalog\Support\InMemoryFeatures;
use PHPUnit\Framework\TestCase;

final class ChangeFeatureSettingsHandlerTest extends TestCase
{
    private InMemoryFeatures $features;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->features = new InMemoryFeatures();
        $this->clock    = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));
    }

    public function testTheChangedSettingsAreSavedWithTheirLines(): void
    {
        self::assertSame(['Statut : Bêta → Disponible'], $this->change(new ChangeFeatureSettings(FeatureCatalogue::ELECTRONIC_INVOICING, 'available', false)));

        $saved = $this->features->settings(FeatureCatalogue::ELECTRONIC_INVOICING);
        self::assertNotNull($saved);
        self::assertSame([FeatureStatus::AVAILABLE, false, $this->clock->now()], [$saved->status(), $saved->isDefaultOn(), $saved->updatedAt()]);
    }

    public function testSettingsUnchangedAreNotSaved(): void
    {
        self::assertSame([], $this->change(new ChangeFeatureSettings(FeatureCatalogue::ONLINE_BOOKING, 'available', true)));
        self::assertSame([], $this->features->savedSettings);
    }

    public function testAStatusIsChosenAmongTheKnownOnes(): void
    {
        try {
            $this->change(new ChangeFeatureSettings(FeatureCatalogue::ONLINE_BOOKING, 'soon', true));
            self::fail('An unknown status was expected to be refused.');
        } catch (\DomainException $e) {
            self::assertSame('Choisis un statut.', $e->getMessage());
        }

        self::assertSame([], $this->features->savedSettings);
    }

    public function testAnUpcomingFeatureIsNotSet(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Une fonctionnalité « À venir » ne se règle pas : son module n’existe pas encore.');

        $this->change(new ChangeFeatureSettings(FeatureCatalogue::CONTROLLED_SUBSTANCES, 'available', true));
    }

    public function testAnUnknownFeatureIsRefused(): void
    {
        $this->expectException(UnknownFeatureException::class);

        $this->change(new ChangeFeatureSettings('portal.teleconsultation', 'available', true));
    }

    /**
     * @return list<string>
     */
    private function change(ChangeFeatureSettings $command): array
    {
        return new ChangeFeatureSettingsHandler($this->features, new FeatureStates($this->features), $this->clock)($command);
    }
}
