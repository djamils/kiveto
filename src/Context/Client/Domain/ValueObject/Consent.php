<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

/**
 * A consent is active as long as it exists: withdrawing it removes it.
 */
final readonly class Consent
{
    /** @var list<ContactChannel> */
    public array $channels;

    /**
     * @param list<ContactChannel> $channels
     */
    public function __construct(
        public ConsentKind $kind,
        array $channels,
        public \DateTimeImmutable $grantedAt,
    ) {
        $unique = [];

        foreach ($channels as $channel) {
            if (!$channel->isAutomated()) {
                throw new \InvalidArgumentException(
                    \sprintf('Channel "%s" cannot carry an automated sending.', $channel->value),
                );
            }

            $unique[$channel->value] = $channel;
        }

        if ([] === $unique) {
            throw new \InvalidArgumentException('A consent needs at least one channel.');
        }

        $this->channels = array_values($unique);
    }

    public function allows(ContactChannel $channel): bool
    {
        return \in_array($channel, $this->channels, true);
    }
}
