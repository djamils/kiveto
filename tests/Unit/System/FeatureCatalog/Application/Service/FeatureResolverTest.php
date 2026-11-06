<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Application\Service;

use App\System\FeatureCatalog\Application\Service\FeatureDecision;
use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureAudience;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureCutOff;
use App\System\FeatureCatalog\Domain\FeatureSettings;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use App\Tests\Unit\System\FeatureCatalog\Support\FakeClinicPlans;
use App\Tests\Unit\System\FeatureCatalog\Support\InMemoryFeatures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeatureResolverTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000f1a1';

    public function testTheSavedSettingsAreInForce(): void
    {
        $features = new InMemoryFeatures();
        $saved    = FeatureSettings::reconstitute(FeatureCatalogue::ELECTRONIC_INVOICING, FeatureStatus::AVAILABLE, true, new \DateTimeImmutable());
        $features->saveSettings($saved);

        self::assertSame($saved, new FeatureResolver($features, new FakeClinicPlans())->settings(FeatureCatalogue::get(FeatureCatalogue::ELECTRONIC_INVOICING)));
    }

    public function testAFeatureNeverSavedHasItsInitialSettings(): void
    {
        $settings = new FeatureResolver(new InMemoryFeatures(), new FakeClinicPlans())->settings(FeatureCatalogue::get(FeatureCatalogue::ELECTRONIC_INVOICING));

        self::assertSame([FeatureStatus::BETA, false, null, FeatureAudience::ALL, null], [$settings->status(), $settings->isDefaultOn(), $settings->updatedAt(), $settings->audience(), $settings->cutOff()]);
    }

    #[DataProvider('provideTheOrderOfTheDecisionCases')]
    public function testTheOrderOfTheDecision(string $code, FeatureStatus $status, bool $cut, FeatureAudience $audience, ?bool $row, ?string $plan, FeatureDecision $expected): void
    {
        $settings = FeatureSettings::reconstitute($code, $status, false, null, $audience, $cut ? new FeatureCutOff('Fuite', new \DateTimeImmutable(), 'Djamil') : null);
        $clinic   = null !== $row ? new ClinicFeature(self::CLINIC, $code, $row, new \DateTimeImmutable(), null, null) : null;

        $decision = FeatureResolver::decide(FeatureCatalogue::get($code), $settings, $clinic, $plan);

        self::assertSame($expected, $decision);
        self::assertSame(\in_array($expected, [FeatureDecision::SET_ON, FeatureDecision::LEGACY_ON], true), $decision->isOn());
    }

    /**
     * @return iterable<string, array{string, FeatureStatus, bool, FeatureAudience, ?bool, ?string, FeatureDecision}>
     */
    public static function provideTheOrderOfTheDecisionCases(): iterable
    {
        $all = FeatureAudience::ALL;

        yield 'switched on' => [FeatureCatalogue::SUPPORT_WRITE_MODE, FeatureStatus::INTERNAL, false, $all, true, null, FeatureDecision::SET_ON];

        yield 'switched off' => [FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, false, $all, false, null, FeatureDecision::SET_OFF];

        yield 'never set, legacy on' => [FeatureCatalogue::ELECTRONIC_INVOICING, FeatureStatus::BETA, false, $all, null, null, FeatureDecision::LEGACY_ON];

        yield 'never set, legacy off' => [FeatureCatalogue::SUPPORT_WRITE_MODE, FeatureStatus::INTERNAL, false, $all, null, null, FeatureDecision::LEGACY_OFF];

        yield 'upcoming, even switched on' => [FeatureCatalogue::HOSPITALISATION, FeatureStatus::UPCOMING, false, $all, true, null, FeatureDecision::UPCOMING];

        yield 'upcoming, even cut' => [FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::UPCOMING, true, $all, null, null, FeatureDecision::UPCOMING];

        yield 'cut off, even switched on' => [FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, true, $all, true, 'paid', FeatureDecision::CUT_OFF];

        yield 'cut off before the audience' => [FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, true, FeatureAudience::PAID, true, 'pilot', FeatureDecision::CUT_OFF];

        yield 'kept for the paid, a pilot' => [FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, false, FeatureAudience::PAID, true, 'pilot', FeatureDecision::OUTSIDE_AUDIENCE];

        yield 'kept for the paid, no account' => [FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, false, FeatureAudience::PAID, null, null, FeatureDecision::OUTSIDE_AUDIENCE];

        yield 'kept for the paid, a paid one' => [FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, false, FeatureAudience::PAID, true, 'paid', FeatureDecision::SET_ON];
    }

    public function testOneClinicsPlanIsReadOnlyForAFeatureKeptForOnePlan(): void
    {
        $features = new InMemoryFeatures();
        $plans    = new FakeClinicPlans([self::CLINIC => 'paid']);
        $resolver = new FeatureResolver($features, $plans);
        $booking  = FeatureCatalogue::get(FeatureCatalogue::ONLINE_BOOKING);

        self::assertSame(FeatureDecision::LEGACY_ON, $resolver->decisionFor($booking, self::CLINIC));
        self::assertSame(0, $plans->reads, 'every clinic: no plan read');

        $settings = FeatureSettings::reconstitute(FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, true, null, FeatureAudience::PILOT);
        $features->saveSettings($settings);

        self::assertSame(FeatureDecision::OUTSIDE_AUDIENCE, $resolver->decisionFor($booking, self::CLINIC));
        self::assertSame(1, $plans->reads);
    }

    public function testThePlansOfAListAreReadOnceAndOnlyWhenNeeded(): void
    {
        $plans    = new FakeClinicPlans([self::CLINIC => 'paid']);
        $resolver = new FeatureResolver(new InMemoryFeatures(), $plans);
        $every    = FeatureSettings::reconstitute(FeatureCatalogue::ONLINE_BOOKING, FeatureStatus::AVAILABLE, true, null);
        $paid     = FeatureSettings::reconstitute(FeatureCatalogue::SMS_REMINDERS, FeatureStatus::AVAILABLE, true, null, FeatureAudience::PAID);

        self::assertSame([], $resolver->plansFor([$every], [self::CLINIC]));
        self::assertSame([], $resolver->plansFor([$every, $paid], []), 'no clinic, no read');
        self::assertSame(0, $plans->reads);
        self::assertSame([self::CLINIC => 'paid'], $resolver->plansFor([$every, $paid], [self::CLINIC]));
        self::assertSame(1, $plans->reads);
    }

    public function testEachDecisionSaysWhatDecided(): void
    {
        self::assertSame(
            ['À venir', 'Coupée partout', 'Hors formule', 'Activée', 'Désactivée', 'Activée (état d’origine)', 'Désactivée (état d’origine)'],
            array_map(static fn (FeatureDecision $d): string => $d->label(), FeatureDecision::cases()),
        );
        self::assertSame(
            [false, false, false, true, true, true, true],
            array_map(static fn (FeatureDecision $d): bool => $d->isTheClinicsOwn(), FeatureDecision::cases()),
        );
    }
}
