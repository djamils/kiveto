<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\SecondFactor;

/**
 * RFC 6238 time-based one-time passwords as every authenticator app makes
 * them: HMAC-SHA1, 30-second steps, 6 digits. A code is accepted one step
 * early or late (clock drift), and never at or before the last step used.
 */
final class Totp
{
    public const int PERIOD = 30;

    public const int DIGITS = 6;

    private const int DRIFT_STEPS = 1;

    public static function stepAt(\DateTimeImmutable $at): int
    {
        return intdiv($at->getTimestamp(), self::PERIOD);
    }

    public static function code(TotpSecret $secret, int $step): string
    {
        $hash   = hash_hmac('sha1', pack('J', $step), $secret->bytes(), true);
        $offset = \ord($hash[19]) & 0x0F;
        $value  = ((\ord($hash[$offset]) & 0x7F) << 24)
            | (\ord($hash[$offset + 1]) << 16)
            | (\ord($hash[$offset + 2]) << 8)
            | \ord($hash[$offset + 3]);

        return str_pad((string) ($value % 10 ** self::DIGITS), self::DIGITS, '0', \STR_PAD_LEFT);
    }

    /**
     * The step the code belongs to, or null when it matches none of the
     * accepted ones (or only steps already used).
     */
    public static function matchingStep(TotpSecret $secret, string $code, \DateTimeImmutable $now, ?int $lastUsedStep): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (1 !== preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return null;
        }

        $current = self::stepAt($now);

        for ($step = $current - self::DRIFT_STEPS; $step <= $current + self::DRIFT_STEPS; ++$step) {
            if (null !== $lastUsedStep && $step <= $lastUsedStep) {
                continue;
            }

            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }
}
