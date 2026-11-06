<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\PrepareSecondFactor;

use App\System\IdentityAccess\Domain\SecondFactor\Totp;

final readonly class SecondFactorEnrollment
{
    public const string ISSUER = 'Kiveto Console';

    public function __construct(
        public string $secret,
        public string $email,
    ) {
    }

    /**
     * The otpauth:// address a QR code carries (Key Uri Format).
     */
    public function uri(): string
    {
        return \sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode(self::ISSUER),
            rawurlencode($this->email),
            $this->secret,
            rawurlencode(self::ISSUER),
            Totp::DIGITS,
            Totp::PERIOD,
        );
    }

    /**
     * The key grouped by four, for typing it by hand.
     */
    public function groupedSecret(): string
    {
        return implode(' ', str_split($this->secret, 4));
    }
}
