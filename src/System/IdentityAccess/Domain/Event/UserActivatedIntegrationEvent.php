<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * An invited user chose a password and became ACTIVE. Clinic recounts the
 * active veterinarians of its clinics (SMS quota).
 */
final readonly class UserActivatedIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'identity-access';
    protected const int    VERSION         = 1;

    /**
     * @param string $occurredAt ATOM date of the activation
     */
    public function __construct(
        public string $userId,
        public string $occurredAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->userId;
    }

    /**
     * @return array<string, string>
     */
    public function payload(): array
    {
        return [
            'userId'     => $this->userId,
            'occurredAt' => $this->occurredAt,
        ];
    }
}
