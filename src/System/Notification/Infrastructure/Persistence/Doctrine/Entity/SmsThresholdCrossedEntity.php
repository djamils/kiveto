<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One row per SMS usage threshold (80, 100, CAP) a
 * clinic crossed in a quota month, inserted with INSERT IGNORE so that the
 * crossing is announced once.
 */
#[ORM\Entity]
#[ORM\Table(name: 'notification__sms_thresholds_crossed')]
class SmsThresholdCrossedEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
        private Uuid $clinicId,
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 7)]
        private string $month,
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 8)]
        private string $threshold,
        #[ORM\Column(name: 'crossed_at', type: 'datetime_immutable')]
        private \DateTimeImmutable $crossedAt,
    ) {
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function getMonth(): string
    {
        return $this->month;
    }

    public function getThreshold(): string
    {
        return $this->threshold;
    }

    public function getCrossedAt(): \DateTimeImmutable
    {
        return $this->crossedAt;
    }
}
