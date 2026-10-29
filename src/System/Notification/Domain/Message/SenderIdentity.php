<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * Who a message is sent as: the e-mail From (name and address), the
 * Reply-To, and the alphanumeric SMS sender.
 */
final readonly class SenderIdentity
{
    public const string FALLBACK_SMS_SENDER = 'Kiveto';

    private function __construct(
        public string $name,
        public string $emailAddress,
        public ?string $replyTo,
        public string $smsSender,
    ) {
    }

    /** Account and test messages: Kiveto itself, no reply-to. */
    public static function kiveto(string $accountSenderAddress): self
    {
        return new self('Kiveto', $accountSenderAddress, null, self::FALLBACK_SMS_SENDER);
    }

    /** A clinic writing to its clients from Kiveto's sending sub-domain. */
    public static function clinic(string $name, string $emailLocalPart, string $emailDomain, ?string $replyTo, string $smsSender): self
    {
        $smsSender = mb_trim($smsSender);

        return new self(
            $name,
            $emailLocalPart . '@' . $emailDomain,
            null !== $replyTo && '' !== mb_trim($replyTo) ? mb_trim($replyTo) : null,
            '' !== $smsSender ? $smsSender : self::FALLBACK_SMS_SENDER,
        );
    }

    public static function reconstitute(string $name, string $emailAddress, ?string $replyTo, string $smsSender): self
    {
        return new self($name, $emailAddress, $replyTo, $smsSender);
    }
}
