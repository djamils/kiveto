<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class AuthorityNotificationScheduled extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'regulatory';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $notificationId,
        public string $admissionId,
        public string $clinicId,
        public string $deadline,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->notificationId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'notificationId' => $this->notificationId,
            'admissionId'    => $this->admissionId,
            'clinicId'       => $this->clinicId,
            'deadline'       => $this->deadline,
        ];
    }
}
