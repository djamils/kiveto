<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class StrayCustodyClosedHandedToAuthority extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'regulatory';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $custodyId,
        public string $admissionId,
        public string $closedAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->custodyId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'custodyId'   => $this->custodyId,
            'admissionId' => $this->admissionId,
            'closedAt'    => $this->closedAt,
        ];
    }
}
