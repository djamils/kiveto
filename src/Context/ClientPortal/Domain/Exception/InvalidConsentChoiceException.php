<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * Channels the consent cannot take: a channel other than `sms` or `email`,
 * or no channel on a kind that is sent anyway. The message is French: the
 * Portal shows it as the violation of `channels`.
 */
final class InvalidConsentChoiceException extends \DomainException
{
    public static function unknownChannel(string $channel): self
    {
        return new self(\sprintf('Canal « %s » non pris en charge : sms ou email.', $channel));
    }

    public static function channelRequired(): self
    {
        return new self('Ces messages sont envoyés dans tous les cas : choisissez au moins un canal.');
    }
}
