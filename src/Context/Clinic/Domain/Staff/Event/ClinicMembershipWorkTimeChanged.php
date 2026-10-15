<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClinicMembershipWorkTimeChanged extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'clinic-staff';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $membershipId,
        private string $clinicId,
        private string $userId,
        private int $workTimePercent,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->membershipId;
    }

    /** @return array<string, int|string> */
    public function payload(): array
    {
        return [
            'membershipId'    => $this->membershipId,
            'clinicId'        => $this->clinicId,
            'userId'          => $this->userId,
            'workTimePercent' => $this->workTimePercent,
        ];
    }
}
