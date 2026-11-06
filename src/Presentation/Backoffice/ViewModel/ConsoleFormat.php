<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel;

/**
 * How the console prints dates, names and addresses, in French and in the
 * admin's display time zone. Pure functions of their arguments (« now »
 * included), shared by the Twig filters and the CSV exports.
 */
final class ConsoleFormat
{
    private const array MONTHS = [
        1 => 'janv.', 2 => 'févr.', 3 => 'mars', 4 => 'avr.', 5 => 'mai', 6 => 'juin',
        7 => 'juil.', 8 => 'août', 9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.',
    ];

    private const array LONG_MONTHS = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

    private const array DAYS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    private const array SHORT_DAYS = [1 => 'lun.', 2 => 'mar.', 3 => 'mer.', 4 => 'jeu.', 5 => 'ven.', 6 => 'sam.', 7 => 'dim.'];

    /** The tints of the clinic marks (indigo, emerald, amber, rose, sky, violet, teal, orange, lime, fuchsia, cyan, pink). */
    private const array TINTS = ['#4f46e5', '#059669', '#d97706', '#e11d48', '#0284c7', '#7c3aed', '#0d9488', '#ea580c', '#65a30d', '#c026d3', '#0891b2', '#db2777'];

    /** Words a clinic's name shares with every other, left out of its initials. */
    private const array CLINIC_WORDS = [
        'clinique', 'clinic', 'cabinet', 'centre', 'hôpital', 'hopital', 'chv', 'vétérinaire', 'veterinaire', 'vétérinaires', 'veterinaires',
        'du', 'de', 'des', 'la', 'le', 'les', 'l', 'd', 'et', 'sas', 'sarl', 'selarl', 'selas', 'llc', 'inc', 'ltd',
    ];

    /**
     * « à l'instant », « il y a 3 min », « il y a 2 h » within the last six
     * hours; then « aujourd'hui, 09:12 », « hier, 18:40 », « 28 oct. »,
     * « 12 oct. 2025 » for another year.
     */
    public static function when(\DateTimeImmutable $at, \DateTimeImmutable $now, \DateTimeZone $zone): string
    {
        $seconds = $now->getTimestamp() - $at->getTimestamp();

        if ($seconds >= 0 && $seconds < 60) {
            return 'à l’instant';
        }

        if ($seconds >= 0 && $seconds < 3600) {
            return \sprintf('il y a %d min', intdiv($seconds, 60));
        }

        if ($seconds >= 0 && $seconds < 6 * 3600) {
            return \sprintf('il y a %d h', intdiv($seconds, 3600));
        }

        $local = $at->setTimezone($zone);
        $today = $now->setTimezone($zone);

        if ($local->format('Y-m-d') === $today->format('Y-m-d')) {
            return 'aujourd’hui, ' . $local->format('H:i');
        }

        if ($local->format('Y-m-d') === $today->modify('-1 day')->format('Y-m-d')) {
            return 'hier, ' . $local->format('H:i');
        }

        return self::shortDate($at, $zone, $local->format('Y') !== $today->format('Y'));
    }

    /**
     * « 14 oct. », or « 14 oct. 2026 » with the year.
     */
    public static function shortDate(\DateTimeImmutable $at, \DateTimeZone $zone, bool $withYear = false): string
    {
        $local = $at->setTimezone($zone);
        $text  = $local->format('j') . ' ' . self::MONTHS[(int) $local->format('n')];

        return $withYear ? $text . ' ' . $local->format('Y') : $text;
    }

    /**
     * « 27 octobre 2026 ».
     */
    public static function longDate(\DateTimeImmutable $at, \DateTimeZone $zone): string
    {
        $local = $at->setTimezone($zone);

        return $local->format('j') . ' ' . self::LONG_MONTHS[(int) $local->format('n')] . ' ' . $local->format('Y');
    }

    /**
     * « mardi 27 octobre 2026 », « mardi 27 octobre 2026, 07:12 » with the time.
     */
    public static function dayDate(\DateTimeImmutable $at, \DateTimeZone $zone, bool $withTime = false): string
    {
        $local = $at->setTimezone($zone);
        $text  = self::DAYS[(int) $local->format('N')] . ' ' . self::longDate($at, $zone);

        return $withTime ? $text . ', ' . $local->format('H:i') : $text;
    }

    /**
     * « mar. 27 oct., 07:12 ».
     */
    public static function shortDayTime(\DateTimeImmutable $at, \DateTimeZone $zone): string
    {
        $local = $at->setTimezone($zone);

        return self::SHORT_DAYS[(int) $local->format('N')] . ' ' . self::shortDate($at, $zone) . ', ' . $local->format('H:i');
    }

    /**
     * « 01/11 02:32 », the date column of the audit log.
     */
    public static function logDate(\DateTimeImmutable $at, \DateTimeZone $zone): string
    {
        return $at->setTimezone($zone)->format('d/m H:i');
    }

    /**
     * Two letters for an avatar: « Dr Claire Martin » → « CM », « Djamil
     * Soidiki » → « DS », « admin@kiveto.local » → « AD ».
     */
    public static function initials(string $name): string
    {
        $name  = trim((string) preg_replace('/@.*$/', '', $name));
        $name  = trim((string) preg_replace('/^(Dr|Pr)\.?\s+/iu', '', $name));
        $words = array_values(array_filter(preg_split('/[\s._\-]+/u', $name) ?: [], static fn (string $w): bool => '' !== $w));

        if ([] === $words) {
            return '?';
        }

        if (1 === \count($words)) {
            return mb_strtoupper(mb_substr($words[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[\count($words) - 1], 0, 1));
    }

    /**
     * Two letters for a clinic's mark, without the words every clinic name
     * shares: « Clinique vétérinaire du Vieux-Port » → « VP », « Clinique
     * de la Côte Bleue » → « CB ».
     */
    public static function clinicInitials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/[\s\'’._\-]+/u', trim($name)) ?: [], static fn (string $w): bool => '' !== $w));
        $kept  = array_values(array_filter($words, static fn (string $w): bool => !\in_array(mb_strtolower($w), self::CLINIC_WORDS, true)));

        return self::initials(implode(' ', [] !== $kept ? $kept : $words));
    }

    /**
     * The colour of a clinic's mark: the brand colour it chose, else one of
     * the console's tints, always the same for the same clinic. The CSS
     * fades it (`.co-initials.is-tinted`).
     */
    public static function tint(string $seed, ?string $brandColor = null): string
    {
        if (null !== $brandColor && 1 === preg_match('/^#[0-9a-f]{6}$/i', $brandColor)) {
            return strtolower($brandColor);
        }

        return self::TINTS[crc32($seed) % \count(self::TINTS)];
    }

    /**
     * An address shown in part: « 91.x.x.12 », « 2001:db8:… ».
     */
    public static function maskedIp(?string $ip): string
    {
        if (null === $ip || '' === $ip) {
            return '—';
        }

        if (false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return $parts[0] . '.x.x.' . $parts[3];
        }

        $groups = explode(':', $ip);

        return $groups[0] . ':' . ($groups[1] ?? '') . ':…';
    }

    /**
     * « 18 min », « 1 h 05 ».
     */
    public static function duration(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds) + 30, 60);

        if ($minutes < 60) {
            return $minutes . ' min';
        }

        return \sprintf('%d h %02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * « 396 € », « 99,50 € » from cents (no currency other than the euro here).
     */
    public static function euros(int $cents): string
    {
        $units = intdiv($cents, 100);
        $rest  = $cents % 100;

        return number_format($units, 0, ',', ' ') . (0 === $rest ? '' : ',' . \sprintf('%02d', $rest)) . ' €';
    }

    /**
     * A typed phone number in the E.164 form the clinic keeps: « 04 91 00 00
     * 00 » becomes +33491000000, « 0032… » +32…; anything else is kept for
     * the domain to refuse. Null when nothing was typed.
     */
    public static function phone(?string $typed): ?string
    {
        $digits = (string) preg_replace('/[\s.\-()]+/', '', (string) $typed);

        return match (true) {
            '' === $digits                          => null,
            str_starts_with($digits, '00')          => '+' . substr($digits, 2),
            1 === preg_match('/^0\d{9}$/', $digits) => '+33' . substr($digits, 1),
            default                                 => $digits,
        };
    }
}
