<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * The payer of an invoice as Billing's messages reach them. Channels are
 * Notification's: `EMAIL`, `SMS`.
 */
final readonly class ClientContact
{
    public const string EMAIL = 'EMAIL';
    public const string SMS   = 'SMS';

    /**
     * @param string       $name                 « Sophie Dubois » (the recipient's name)
     * @param string       $addressee            « Madame Dubois » (the greeting)
     * @param string|null  $mobile               E.164
     * @param list<string> $billingChannels      the reachable channels the « Facturation » consent allows
     * @param string|null  $transactionalChannel preferred channel when reachable, else e-mail, else SMS; null: unreachable
     */
    public function __construct(
        public string $clientId,
        public string $name,
        public string $addressee,
        public ?string $email,
        public ?string $mobile,
        public array $billingChannels,
        public ?string $transactionalChannel,
        public bool $archived,
    ) {
    }

    /** « Envoyer »: the invoice goes by e-mail only. */
    public function canReceiveInvoice(): bool
    {
        return !$this->archived && null !== $this->email;
    }

    /**
     * The channels of an unpaid reminder: the « Facturation » consent's, else
     * the transactional channel.
     *
     * @return list<string>
     */
    public function reminderChannels(): array
    {
        if ($this->archived) {
            return [];
        }

        if ([] !== $this->billingChannels) {
            return $this->billingChannels;
        }

        return null !== $this->transactionalChannel ? [$this->transactionalChannel] : [];
    }
}
