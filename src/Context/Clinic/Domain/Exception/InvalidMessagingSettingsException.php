<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Exception;

final class InvalidMessagingSettingsException extends \DomainException
{
    public const string FIELD_SENDER = 'sender';
    public const string FIELD_CAP    = 'cap';

    private function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function malformedSender(string $sender): self
    {
        return new self(\sprintf('SMS sender "%s" must be 1 to 11 letters, digits or spaces, with at least one letter.', $sender), self::FIELD_SENDER);
    }

    public static function implausibleOverageCap(int $segments): self
    {
        return new self(\sprintf('An overage cap of %d segments is outside 0 to 100000.', $segments), self::FIELD_CAP);
    }
}
