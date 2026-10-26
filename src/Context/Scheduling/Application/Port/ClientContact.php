<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * The owner of an appointment as its messages reach them. Channels are
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
     * @param string|null  $transactionalChannel the one channel of a confirmation, change or cancellation
     *                                           (preferred channel when reachable, else e-mail, else SMS); null: unreachable
     * @param list<string> $reminderChannels     the reachable channels the « Rappels de rendez-vous » consent allows
     */
    public function __construct(
        public string $clientId,
        public string $name,
        public string $addressee,
        public ?string $email,
        public ?string $mobile,
        public ?string $transactionalChannel,
        public array $reminderChannels,
        public bool $archived,
    ) {
    }

    public function acceptsReminderOn(string $channel): bool
    {
        return \in_array($channel, $this->reminderChannels, true);
    }
}
