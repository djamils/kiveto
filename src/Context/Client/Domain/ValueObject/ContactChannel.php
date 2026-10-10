<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum ContactChannel: string
{
    case SMS   = 'sms';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case MAIL  = 'mail';

    public function label(): string
    {
        return match ($this) {
            self::SMS   => 'SMS',
            self::EMAIL => 'E-mail',
            self::PHONE => 'Téléphone',
            self::MAIL  => 'Courrier',
        };
    }

    /**
     * Only these channels can carry an automated sending, hence a consent.
     */
    public function isAutomated(): bool
    {
        return self::SMS === $this || self::EMAIL === $this;
    }
}
