<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Rows are read and written in SQL by DbalReminderSettingsRepository.
 */
#[ORM\Entity]
#[ORM\Table(name: 'scheduling__clinic_settings')]
class ClinicSchedulingSettingsEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        private Uuid $clinicId,
        #[ORM\Column(name: 'reminder_delay', type: 'string', length: 16, options: ['default' => 'H24'])]
        private string $reminderDelay = 'H24',
    ) {
    }

    /**
     * @return array{clinic_id: Uuid, reminder_delay: string}
     */
    public function columns(): array
    {
        return ['clinic_id' => $this->clinicId, 'reminder_delay' => $this->reminderDelay];
    }
}
