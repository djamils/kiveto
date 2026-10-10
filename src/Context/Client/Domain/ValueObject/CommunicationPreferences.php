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
}
