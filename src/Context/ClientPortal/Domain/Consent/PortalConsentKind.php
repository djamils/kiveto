<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Consent;

/**
 * The consent kinds the Portal shows (contract « Consentements »), in the
 * order of the screen. An optional kind controls the sending: the owner may
 * withdraw it entirely. The other kinds are sent anyway, their consent only
 * picks the channel, so at least one channel stays.
 */
enum PortalConsentKind: string
{
    case APPOINTMENT_REMINDERS = 'appointment_reminders';
    case VACCINATION_REMINDERS = 'vaccination_reminders';
    case CLINICAL_DOCUMENTS    = 'clinical_documents';
    case BILLING               = 'billing';
    case MARKETING             = 'marketing';

    /** The automated channels a consent may cover, in display order. */
    public const array CHANNELS = ['sms', 'email'];

    public const string SEND_CONTROL = 'send_control';

    public const string CHANNEL_PREFERENCE = 'channel_preference';

    public function isOptional(): bool
    {
        return \in_array($this, [self::MARKETING, self::APPOINTMENT_REMINDERS, self::VACCINATION_REMINDERS], true);
    }

    /** `send_control` for an optional kind, `channel_preference` otherwise. */
    public function effect(): string
    {
        return $this->isOptional() ? self::SEND_CONTROL : self::CHANNEL_PREFERENCE;
    }

    /**
     * The channels in display order, each once; anything else than `sms`
     * and `email` is dropped.
     *
     * @param list<string> $channels
     *
     * @return list<string>
     */
    public static function orderedChannels(array $channels): array
    {
        return array_values(array_filter(self::CHANNELS, static fn (string $channel): bool => \in_array($channel, $channels, true)));
    }
}
