<?php

declare(strict_types=1);

namespace App\Fixtures\Scheduling\Story;

use App\Fixtures\Scheduling\AppointmentFactory;
use App\Fixtures\Scheduling\WaitingRoomEntryFactory;
use Zenstruck\Foundry\Story;

final class SchedulingStory extends Story
{
    public function __construct(
        private readonly AppointmentFactory $appointmentFactory,
        private readonly WaitingRoomEntryFactory $waitingRoomEntryFactory,
    ) {
    }

    public function build(): void
    {
        // Note: This story assumes clinics, users, owners, and animals exist from other BC fixtures
        // Typical usage: load AccessControl, Clinic, Client, and Animal fixtures first

        // Example clinic/user/owner/animal IDs (would come from other stories in reality)
        // For now, this is a template - actual IDs should be injected or retrieved

        // Uncomment and adjust when integrating with other BC fixtures:
    }
}
