<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Booking;

use App\Context\Scheduling\Domain\Booking\InvalidBookingConfigurationException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OnlineBookingSettingsTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';

    public function testDefaults(): void
    {
        $settings = OnlineBookingSettings::defaults(ClinicId::fromString(self::CLINIC));

        self::assertSame(self::CLINIC, $settings->clinicId()->toString());
        self::assertFalse($settings->isEnabled());
        self::assertSame(15, $settings->gridStepMinutes());
        self::assertSame(120, $settings->minLeadTimeMinutes());
        self::assertSame(24, $settings->minLeadTimeManualHours());
        self::assertSame(60, $settings->horizonDays());
        self::assertSame(24, $settings->cancellationWindowHours());
        self::assertFalse($settings->isNewClientBookingEnabled());
        self::assertSame('PT2H', OnlineBookingSettings::AUTO_DECLINE_MARGIN);
    }

    /**
     * @param array<string, int> $overrides
     */
    #[DataProvider('provideBoundsAreAcceptedCases')]
    public function testBoundsAreAccepted(array $overrides): void
    {
        $settings = $this->settings($overrides);

        $field = (string) array_key_first($overrides);
        self::assertSame($overrides[$field] ?? null, match ($field) {
            'gridStepMinutes'         => $settings->gridStepMinutes(),
            'minLeadTimeMinutes'      => $settings->minLeadTimeMinutes(),
            'minLeadTimeManualHours'  => $settings->minLeadTimeManualHours(),
            'horizonDays'             => $settings->horizonDays(),
            'cancellationWindowHours' => $settings->cancellationWindowHours(),
            default                   => null,
        });
    }

    /**
     * @return iterable<string, array{array<string, int>}>
     */
    public static function provideBoundsAreAcceptedCases(): iterable
    {
        foreach ([5, 10, 15, 20, 30] as $step) {
            yield 'grid ' . $step => [['gridStepMinutes' => $step]];
        }

        yield 'lead time 0' => [['minLeadTimeMinutes' => 0]];
        yield 'lead time 2880' => [['minLeadTimeMinutes' => 2880]];
        yield 'manual lead time 3' => [['minLeadTimeManualHours' => 3]];
        yield 'manual lead time 168' => [['minLeadTimeManualHours' => 168]];
        yield 'horizon 1' => [['horizonDays' => 1]];
        yield 'horizon 180' => [['horizonDays' => 180]];
        yield 'cancellation 0' => [['cancellationWindowHours' => 0]];
        yield 'cancellation 168' => [['cancellationWindowHours' => 168]];
    }

    /**
     * @param array<string, int> $overrides
     */
    #[DataProvider('provideOutOfBoundsValuesAreRefusedWithAFrenchMessageCases')]
    public function testOutOfBoundsValuesAreRefusedWithAFrenchMessage(array $overrides, string $message): void
    {
        try {
            $this->settings($overrides);
            self::fail('The settings should be refused.');
        } catch (InvalidBookingConfigurationException $e) {
            self::assertSame(array_key_first($overrides), $e->field);
            self::assertSame($message, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, int>, string}>
     */
    public static function provideOutOfBoundsValuesAreRefusedWithAFrenchMessageCases(): iterable
    {
        $grid = 'Le pas de la grille doit être de 5, 10, 15, 20 ou 30 minutes.';
        foreach ([0, 1, 25, 45, 60] as $step) {
            yield 'grid ' . $step => [['gridStepMinutes' => $step], $grid];
        }

        $lead = 'Le délai minimum de réservation doit être compris entre 0 et 2880 minutes.';
        yield 'lead time -1' => [['minLeadTimeMinutes' => -1], $lead];
        yield 'lead time 2881' => [['minLeadTimeMinutes' => 2881], $lead];

        $manual = 'Le délai minimum d\'une demande à confirmer doit être compris entre 3 et 168 heures.';
        yield 'manual lead time 2' => [['minLeadTimeManualHours' => 2], $manual];
        yield 'manual lead time 169' => [['minLeadTimeManualHours' => 169], $manual];

        $horizon = 'L\'horizon de réservation doit être compris entre 1 et 180 jours.';
        yield 'horizon 0' => [['horizonDays' => 0], $horizon];
        yield 'horizon 181' => [['horizonDays' => 181], $horizon];

        $cancellation = 'Le délai d\'annulation doit être compris entre 0 et 168 heures.';
        yield 'cancellation -1' => [['cancellationWindowHours' => -1], $cancellation];
        yield 'cancellation 169' => [['cancellationWindowHours' => 169], $cancellation];
    }

    public function testPendingExpiryIsTheEarlierOfFortyEightHoursAndTheMarginBeforeTheStart(): void
    {
        $settings  = OnlineBookingSettings::defaults(ClinicId::fromString(self::CLINIC));
        $createdAt = new \DateTimeImmutable('2026-11-01 10:00:00', new \DateTimeZone('UTC'));

        // Start in 5 days: 48 h after the creation wins.
        self::assertSame('2026-11-03 10:00', $settings->pendingExpiry($createdAt, $createdAt->modify('+5 days'))->format('Y-m-d H:i'));
        // Start tomorrow 09:00: 2 h before the start wins.
        self::assertSame('2026-11-02 07:00', $settings->pendingExpiry($createdAt, new \DateTimeImmutable('2026-11-02 09:00:00', new \DateTimeZone('UTC')))->format('Y-m-d H:i'));
        // Exactly 50 h ahead: both are equal.
        self::assertSame('2026-11-03 10:00', $settings->pendingExpiry($createdAt, $createdAt->modify('+50 hours'))->format('Y-m-d H:i'));
    }

    /**
     * AC 87: with a start at least `minLeadTimeManualHours` (≥ 3 h) after the
     * creation, a pending booking never expires at (or before) its creation.
     */
    public function testPendingExpiryIsAlwaysAfterTheCreationOverTheBounds(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-29 00:17:00', new \DateTimeZone('UTC'));

        for ($manualHours = OnlineBookingSettings::MIN_LEAD_TIME_MANUAL_HOURS_MIN; $manualHours <= OnlineBookingSettings::MIN_LEAD_TIME_MANUAL_HOURS_MAX; ++$manualHours) {
            $settings = $this->settings(['minLeadTimeManualHours' => $manualHours]);
            $earliest = $createdAt->modify(\sprintf('+%d hours', $settings->minLeadTimeManualHours()));

            foreach ([0, 1, 5, 59, 60, 61, 119, 120, 121, 2879, 2880, 2881, 10_000, 259_200] as $extraMinutes) {
                $startsAt = $earliest->modify(\sprintf('+%d minutes', $extraMinutes));
                $expiry   = $settings->pendingExpiry($createdAt, $startsAt);

                self::assertGreaterThan($createdAt, $expiry, \sprintf('manual %d h, +%d min', $manualHours, $extraMinutes));
                self::assertLessThan($startsAt, $expiry);
            }
        }
    }

    public function testPendingMinLeadTimeIsTheLongerOfBothLeadTimes(): void
    {
        self::assertSame(1440, $this->settings([])->pendingMinLeadTimeMinutes());
        self::assertSame(2880, $this->settings(['minLeadTimeMinutes' => 2880])->pendingMinLeadTimeMinutes());
    }

    public function testOpenForBookingNeedsTheSettingAndAnActiveClinic(): void
    {
        $enabled  = $this->settings([], enabled: true);
        $disabled = $this->settings([], enabled: false);

        self::assertTrue($enabled->isOpenForBooking(true));
        self::assertFalse($enabled->isOpenForBooking(false));
        self::assertFalse($disabled->isOpenForBooking(true));
        self::assertFalse($disabled->isOpenForBooking(false));
        self::assertTrue($enabled->isEnabled());
    }

    public function testNewClientBookingFlagIsKept(): void
    {
        self::assertTrue($this->settings([], newClients: true)->isNewClientBookingEnabled());
    }

    /**
     * @param array<string, int> $overrides
     */
    private function settings(array $overrides, bool $enabled = false, bool $newClients = false): OnlineBookingSettings
    {
        return new OnlineBookingSettings(
            clinicId: ClinicId::fromString(self::CLINIC),
            enabled: $enabled,
            gridStepMinutes: $overrides['gridStepMinutes'] ?? 15,
            minLeadTimeMinutes: $overrides['minLeadTimeMinutes'] ?? 120,
            minLeadTimeManualHours: $overrides['minLeadTimeManualHours'] ?? 24,
            horizonDays: $overrides['horizonDays'] ?? 60,
            cancellationWindowHours: $overrides['cancellationWindowHours'] ?? 24,
            newClientBookingEnabled: $newClients,
        );
    }
}
