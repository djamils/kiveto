<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\UpdateClientCommunicationPreferences;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Replaces the preferred channel and every consent of a client (the
 * VetApp sheet). Each channel granted or withdrawn is written to the
 * consent history with the source and the author given here.
 */
final readonly class UpdateClientCommunicationPreferences implements CommandInterface
{
    /**
     * @param array<string, list<string>> $channelsByConsentKind consent kind => channels the client agreed to;
     *                                                           a kind that is absent or empty is withdrawn
     * @param string                      $source                ConsentSource value: portal, clinic or system
     * @param string                      $actorType             ConsentActorType value: portal_user, staff or system
     * @param int|null                    $expectedVersion       the client version the form was rendered at; a
     *                                                           client changed since is refused (StaleConsentsException)
     */
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public ?string $preferredChannel,
        public array $channelsByConsentKind,
        public string $source,
        public string $actorType,
        public ?string $actorId,
        public string $authorLabel,
        public ?int $expectedVersion = null,
    ) {
    }
}
