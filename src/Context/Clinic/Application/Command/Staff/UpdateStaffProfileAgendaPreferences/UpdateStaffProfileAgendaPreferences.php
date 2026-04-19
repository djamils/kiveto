<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\UpdateStaffProfileAgendaPreferences;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateStaffProfileAgendaPreferences implements CommandInterface
{
    public function __construct(
        public string $profileId,
        public string $agendaColor,
        public int $sortOrder,
        public bool $isVisibleInAgenda,
    ) {
    }
}
