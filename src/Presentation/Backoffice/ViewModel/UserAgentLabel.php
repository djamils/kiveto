<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel;

/**
 * « Chrome · macOS », « Safari · iPhone » from a User-Agent header: enough
 * to tell one's browsers apart, no fingerprinting.
 */
final class UserAgentLabel
{
    public static function of(?string $userAgent): string
    {
        if (null === $userAgent || '' === trim($userAgent)) {
            return 'Navigateur inconnu';
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/')                                           => 'Edge',
            str_contains($userAgent, 'OPR/')                                           => 'Opera',
            str_contains($userAgent, 'Firefox/') || str_contains($userAgent, 'FxiOS/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/')  => 'Chrome',
            str_contains($userAgent, 'Safari/')                                        => 'Safari',
            default                                                                    => 'Navigateur',
        };

        $system = match (true) {
            str_contains($userAgent, 'iPhone')                                            => 'iPhone',
            str_contains($userAgent, 'iPad')                                              => 'iPad',
            str_contains($userAgent, 'Android')                                           => 'Android',
            str_contains($userAgent, 'Mac OS X') || str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Windows')                                           => 'Windows',
            str_contains($userAgent, 'Linux')                                             => 'Linux',
            default                                                                       => null,
        };

        return null !== $system ? $browser . ' · ' . $system : $browser;
    }
}
