<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClientAuthorizedPersonChanged extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'client';
    protected const int    VERSION         = 1;

    /**
     * @param array{
     *     civility: string,
     *     firstName: string,
     *     lastName: string,
     *     relationship: string,
     *     phoneNumber: string|null,
     *     authorizedAt: string
     * }|null $authorizedPerson
     */
    public function __construct(
        private string $clientId,
        private string $clinicId,
        private ?array $authorizedPerson,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clientId;
    }

    public function payload(): array
    {
        return [
            'clientId'         => $this->clientId,
            'clinicId'         => $this->clinicId,
            'authorizedPerson' => $this->authorizedPerson,
        ];
    }
}
