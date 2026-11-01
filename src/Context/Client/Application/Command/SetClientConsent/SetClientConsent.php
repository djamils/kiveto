<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\SetClientConsent;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Sets the channels of one consent kind of a client, the other kinds and
 * the preferred channel untouched (the Portal's per-kind switch). An
 * optional kind (`marketing`, `appointment_reminders`,
 * `vaccination_reminders`) with no channel is withdrawn; `billing` and
 * `clinical_documents` always keep at least one channel
 * (ConsentChannelRequiredException). Channels are `sms` and `email` only
 * (InvalidConsentChannelException). An archived client is refused
 * (ClientArchivedCannotBeModifiedException). Each channel granted or
 * withdrawn is written to the consent history.
 */
final readonly class SetClientConsent implements CommandInterface
{
    /**
     * @param string       $kind           ConsentKind value
     * @param list<string> $channels       `sms` and / or `email`; empty withdraws an optional kind
     * @param string       $source         ConsentSource value: portal, clinic or system
     * @param string       $actorType      ConsentActorType value: portal_user, staff or system
     * @param string|null  $actorId        UUID of the user, null for the system
     * @param string       $authorLabel    the name the history shows
     * @param string|null  $wordingVersion version of the consent wording the owner saw, when known
     */
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public string $kind,
        public array $channels,
        public string $source,
        public string $actorType,
        public ?string $actorId,
        public string $authorLabel,
        public ?string $wordingVersion = null,
    ) {
    }
}
