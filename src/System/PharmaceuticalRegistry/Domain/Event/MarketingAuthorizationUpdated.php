<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

final readonly class MarketingAuthorizationUpdated extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'pharmaceutical_registry';
    protected const int    VERSION         = 1;

    /**
     * @param string[] $changedFields
     */
    public function __construct(
        public string $marketingAuthorizationId,
        public array $changedFields,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->marketingAuthorizationId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'marketingAuthorizationId' => $this->marketingAuthorizationId,
            'changedFields'            => $this->changedFields,
        ];
    }
}
