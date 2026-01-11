<?php

declare(strict_types=1);

namespace App\AccessControl\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClinicMembershipDisabled extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'clinic-access';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $membershipId,
        private string $clinicId,
        private string $userId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->membershipId;
    }

    public function payload(): array
    {
        return [
            'membershipId' => $this->membershipId,
            'clinicId'     => $this->clinicId,
            'userId'       => $this->userId,
        ];
    }
}
