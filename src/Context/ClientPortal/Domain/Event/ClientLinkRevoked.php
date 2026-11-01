<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * An owner account no longer reaches this clinic client: access withdrawn
 * (client archived, account deleted) or a pending link rejected by the
 * clinic (`link_rejected`).
 */
final readonly class ClientLinkRevoked extends AbstractDomainEvent
{
    public const string REASON_WITHDRAWN = 'access_withdrawn';
    public const string REASON_REJECTED  = 'link_rejected';

    protected const string BOUNDED_CONTEXT = 'client_portal';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $linkId,
        public string $userId,
        public string $clinicId,
        public string $clientId,
        public string $reason = self::REASON_WITHDRAWN,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->linkId;
    }

    public function payload(): array
    {
        return [
            'linkId'   => $this->linkId,
            'userId'   => $this->userId,
            'clinicId' => $this->clinicId,
            'clientId' => $this->clientId,
            'reason'   => $this->reason,
        ];
    }
}
