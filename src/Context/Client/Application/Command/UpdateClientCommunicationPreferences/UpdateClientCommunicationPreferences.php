<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\UpdateClientCommunicationPreferences;

final readonly class UpdateClientCommunicationPreferences
{
    /**
     * @param array<string, list<string>> $channelsByConsentKind consent kind => channels the client agreed to;
     *                                                           a kind that is absent or empty is withdrawn
     */
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public ?string $preferredChannel,
        public array $channelsByConsentKind,
    ) {
    }
}
