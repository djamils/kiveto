<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

final readonly class PrescriptionRequirementChanged extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'pharmaceutical_registry';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $presentationId,
        public string $marketingAuthorizationId,
        public string $newClass,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->presentationId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'presentationId'           => $this->presentationId,
            'marketingAuthorizationId' => $this->marketingAuthorizationId,
            'newClass'                 => $this->newClass,
        ];
    }
}
