<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One row per clinic that saved its online booking settings; none means the defaults.
 */
#[ORM\Entity]
#[ORM\Table(name: 'scheduling__online_booking_settings')]
class OnlineBookingSettingsEntity
{
    #[ORM\Column(type: 'boolean')]
    private bool $enabled = false;

    #[ORM\Column(name: 'grid_step_minutes', type: 'integer')]
    private int $gridStepMinutes = 15;

    #[ORM\Column(name: 'min_lead_time_minutes', type: 'integer')]
    private int $minLeadTimeMinutes = 120;

    #[ORM\Column(name: 'min_lead_time_manual_hours', type: 'integer')]
    private int $minLeadTimeManualHours = 24;

    #[ORM\Column(name: 'horizon_days', type: 'integer')]
    private int $horizonDays = 60;

    #[ORM\Column(name: 'cancellation_window_hours', type: 'integer')]
    private int $cancellationWindowHours = 24;

    #[ORM\Column(name: 'new_client_booking_enabled', type: 'boolean')]
    private bool $newClientBookingEnabled = false;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        private Uuid $clinicId,
    ) {
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getGridStepMinutes(): int
    {
        return $this->gridStepMinutes;
    }

    public function setGridStepMinutes(int $gridStepMinutes): void
    {
        $this->gridStepMinutes = $gridStepMinutes;
    }

    public function getMinLeadTimeMinutes(): int
    {
        return $this->minLeadTimeMinutes;
    }

    public function setMinLeadTimeMinutes(int $minLeadTimeMinutes): void
    {
        $this->minLeadTimeMinutes = $minLeadTimeMinutes;
    }

    public function getMinLeadTimeManualHours(): int
    {
        return $this->minLeadTimeManualHours;
    }

    public function setMinLeadTimeManualHours(int $minLeadTimeManualHours): void
    {
        $this->minLeadTimeManualHours = $minLeadTimeManualHours;
    }

    public function getHorizonDays(): int
    {
        return $this->horizonDays;
    }

    public function setHorizonDays(int $horizonDays): void
    {
        $this->horizonDays = $horizonDays;
    }

    public function getCancellationWindowHours(): int
    {
        return $this->cancellationWindowHours;
    }

    public function setCancellationWindowHours(int $cancellationWindowHours): void
    {
        $this->cancellationWindowHours = $cancellationWindowHours;
    }

    public function isNewClientBookingEnabled(): bool
    {
        return $this->newClientBookingEnabled;
    }

    public function setNewClientBookingEnabled(bool $newClientBookingEnabled): void
    {
        $this->newClientBookingEnabled = $newClientBookingEnabled;
    }
}
