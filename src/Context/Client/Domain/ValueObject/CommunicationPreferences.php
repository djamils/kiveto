<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

final readonly class CommunicationPreferences
{
    /** @var list<Consent> */
    public array $consents;

    /**
     * @param list<Consent> $consents
     */
    public function __construct(
        public ?ContactChannel $preferredChannel,
        array $consents,
    ) {
        $seen = [];

        foreach ($consents as $consent) {
            if (isset($seen[$consent->kind->value])) {
                throw new \InvalidArgumentException(
                    \sprintf('Consent "%s" is declared twice.', $consent->kind->value),
                );
            }

            $seen[$consent->kind->value] = true;
        }

        $this->consents = $consents;
    }

    public static function none(): self
    {
        return new self(null, []);
    }

    public function consentFor(ConsentKind $kind): ?Consent
    {
        foreach ($this->consents as $consent) {
            if ($consent->kind === $kind) {
                return $consent;
            }
        }

        return null;
    }

    /**
     * Builds the next preferences. A consent keeps its original date as long
     * as it covers no channel it did not cover before: agreeing to a new
     * channel is a new consent, dated today. A kind left without channel is
     * withdrawn.
     *
     * @param array<string, list<ContactChannel>> $channelsByKind keyed by ConsentKind value
     */
    public function revise(?ContactChannel $preferredChannel, array $channelsByKind, \DateTimeImmutable $now): self
    {
        $consents = [];

        foreach (ConsentKind::cases() as $kind) {
            $channels = $channelsByKind[$kind->value] ?? [];

            if ([] === $channels) {
                continue;
            }

            $current  = $this->consentFor($kind);
            $extended = null === $current || [] !== array_filter(
                $channels,
                static fn (ContactChannel $channel): bool => !$current->allows($channel),
            );

            $consents[] = new Consent($kind, $channels, $extended ? $now : $current->grantedAt);
        }

        return new self($preferredChannel, $consents);
    }

    /**
     * The same preferences with one kind changed: its channels replaced, or
     * the consent withdrawn when none is left. The other kinds and the
     * preferred channel are kept.
     *
     * @param list<ContactChannel> $channels
     */
    public function withConsent(ConsentKind $kind, array $channels, \DateTimeImmutable $now): self
    {
        $channelsByKind = [];

        foreach ($this->consents as $consent) {
            $channelsByKind[$consent->kind->value] = $consent->channels;
        }

        $channelsByKind[$kind->value] = $channels;

        return $this->revise($this->preferredChannel, $channelsByKind, $now);
    }

    /**
     * What moving to the next preferences grants and withdraws, one change
     * per kind and channel, in kind then channel order. The same consents
     * give no change, whatever their dates.
     *
     * @return list<ConsentChange>
     */
    public function changesTo(self $next): array
    {
        $changes = [];

        foreach (ConsentKind::cases() as $kind) {
            $before = $this->consentFor($kind);
            $after  = $next->consentFor($kind);

            foreach (ContactChannel::cases() as $channel) {
                $had = null !== $before && $before->allows($channel);
                $has = null !== $after && $after->allows($channel);

                if ($had !== $has) {
                    $changes[] = new ConsentChange($kind, $channel, $has ? ConsentChangeAction::GRANTED : ConsentChangeAction::WITHDRAWN);
                }
            }
        }

        return $changes;
    }
}
