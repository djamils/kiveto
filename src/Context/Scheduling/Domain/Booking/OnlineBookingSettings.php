<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;

/**
 * A clinic's online booking settings (S4c, S4f): whether the Portal takes
 * bookings, the slot grid, the lead times, the horizon and the cancellation
 * window. A clinic that never saved any gets `defaults()`.
 */
final class OnlineBookingSettings
{
    /** A pending booking is auto-declined at the latest this long before its start (P2). */
    public const string AUTO_DECLINE_MARGIN = 'PT2H';

    /** A pending booking is auto-declined at the latest this long after its creation (P2). */
    public const string PENDING_MAX_AGE = 'PT48H';

    /** @var list<int> */
    public const array GRID_STEPS_MINUTES = [5, 10, 15, 20, 30];

    public const int MIN_LEAD_TIME_MINUTES_MAX          = 2880;
    public const int MIN_LEAD_TIME_MANUAL_HOURS_MIN     = 3;
    public const int MIN_LEAD_TIME_MANUAL_HOURS_MAX     = 168;
    public const int HORIZON_DAYS_MIN                   = 1;
    public const int HORIZON_DAYS_MAX                   = 180;
    public const int CANCELLATION_WINDOW_HOURS_MAX      = 168;
    public const int DEFAULT_GRID_STEP_MINUTES          = 15;
    public const int DEFAULT_MIN_LEAD_TIME_MINUTES      = 120;
    public const int DEFAULT_MIN_LEAD_TIME_MANUAL_HOURS = 24;
    public const int DEFAULT_HORIZON_DAYS               = 60;
    public const int DEFAULT_CANCELLATION_WINDOW_HOURS  = 24;

    private ClinicId $clinicId;
    private bool $enabled;
    private int $gridStepMinutes;
    private int $minLeadTimeMinutes;
    private int $minLeadTimeManualHours;
    private int $horizonDays;
    private int $cancellationWindowHours;
    private bool $newClientBookingEnabled;

    /**
     * @throws InvalidBookingConfigurationException
     */
    public function __construct(
        ClinicId $clinicId,
        bool $enabled,
        int $gridStepMinutes,
        int $minLeadTimeMinutes,
        int $minLeadTimeManualHours,
        int $horizonDays,
        int $cancellationWindowHours,
        bool $newClientBookingEnabled,
    ) {
        if (!\in_array($gridStepMinutes, self::GRID_STEPS_MINUTES, true)) {
            throw new InvalidBookingConfigurationException('gridStepMinutes', 'Le pas de la grille doit être de 5, 10, 15, 20 ou 30 minutes.');
        }

        self::assertBetween($minLeadTimeMinutes, 0, self::MIN_LEAD_TIME_MINUTES_MAX, 'minLeadTimeMinutes', 'Le délai minimum de réservation doit être compris entre %d et %d minutes.');
        self::assertBetween($minLeadTimeManualHours, self::MIN_LEAD_TIME_MANUAL_HOURS_MIN, self::MIN_LEAD_TIME_MANUAL_HOURS_MAX, 'minLeadTimeManualHours', 'Le délai minimum d\'une demande à confirmer doit être compris entre %d et %d heures.');
        self::assertBetween($horizonDays, self::HORIZON_DAYS_MIN, self::HORIZON_DAYS_MAX, 'horizonDays', 'L\'horizon de réservation doit être compris entre %d et %d jours.');
        self::assertBetween($cancellationWindowHours, 0, self::CANCELLATION_WINDOW_HOURS_MAX, 'cancellationWindowHours', 'Le délai d\'annulation doit être compris entre %d et %d heures.');

        $this->clinicId                = $clinicId;
        $this->enabled                 = $enabled;
        $this->gridStepMinutes         = $gridStepMinutes;
        $this->minLeadTimeMinutes      = $minLeadTimeMinutes;
        $this->minLeadTimeManualHours  = $minLeadTimeManualHours;
        $this->horizonDays             = $horizonDays;
        $this->cancellationWindowHours = $cancellationWindowHours;
        $this->newClientBookingEnabled = $newClientBookingEnabled;
    }

    public static function defaults(ClinicId $clinicId): self
    {
        return new self(
            clinicId: $clinicId,
            enabled: false,
            gridStepMinutes: self::DEFAULT_GRID_STEP_MINUTES,
            minLeadTimeMinutes: self::DEFAULT_MIN_LEAD_TIME_MINUTES,
            minLeadTimeManualHours: self::DEFAULT_MIN_LEAD_TIME_MANUAL_HOURS,
            horizonDays: self::DEFAULT_HORIZON_DAYS,
            cancellationWindowHours: self::DEFAULT_CANCELLATION_WINDOW_HOURS,
            newClientBookingEnabled: false,
        );
    }

    /**
     * When a pending booking is auto-declined:
     * min(createdAt + 48 h, startsAt − AUTO_DECLINE_MARGIN). With a start at
     * least `minLeadTimeManualHours` (≥ 3 h) after the creation, it is always
     * later than the creation.
     */
    public function pendingExpiry(\DateTimeImmutable $createdAt, \DateTimeImmutable $startsAt): \DateTimeImmutable
    {
        $maxAge = $createdAt->add(new \DateInterval(self::PENDING_MAX_AGE));
        $margin = $startsAt->sub(new \DateInterval(self::AUTO_DECLINE_MARGIN));

        return min($maxAge, $margin);
    }

    /**
     * The earliest start of a booking that would be pending: max of both lead times.
     */
    public function pendingMinLeadTimeMinutes(): int
    {
        return max($this->minLeadTimeMinutes, $this->minLeadTimeManualHours * 60);
    }

    public function isOpenForBooking(bool $clinicActive): bool
    {
        return $this->enabled && $clinicActive;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function gridStepMinutes(): int
    {
        return $this->gridStepMinutes;
    }

    public function minLeadTimeMinutes(): int
    {
        return $this->minLeadTimeMinutes;
    }

    public function minLeadTimeManualHours(): int
    {
        return $this->minLeadTimeManualHours;
    }

    public function horizonDays(): int
    {
        return $this->horizonDays;
    }

    public function cancellationWindowHours(): int
    {
        return $this->cancellationWindowHours;
    }

    public function isNewClientBookingEnabled(): bool
    {
        return $this->newClientBookingEnabled;
    }

    private static function assertBetween(int $value, int $min, int $max, string $field, string $message): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidBookingConfigurationException($field, \sprintf($message, $min, $max));
        }
    }
}
