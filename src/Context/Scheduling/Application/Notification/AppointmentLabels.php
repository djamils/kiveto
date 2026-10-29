<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Notification;

/**
 * The dates and times of appointment messages, in French and in the clinic's
 * time zone (the given times are already converted to it).
 */
final class AppointmentLabels
{
    private const array DAYS       = [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    private const array SHORT_DAYS = [1 => 'Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.', 'Dim.'];
    /** Titles a printed name may start with (lowercase, without the dot). */
    private const array TITLES      = ['dr', 'docteur', 'pr', 'professeur'];
    private const array MONTHS      = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    private const array SHORT_MONTH = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    /** « 9h30 », « 11h00 ». */
    public static function time(\DateTimeImmutable $at): string
    {
        return $at->format('G') . 'h' . $at->format('i');
    }

    /** « 9h30 · 30 min ». */
    public static function timeAndDuration(\DateTimeImmutable $at, int $durationMinutes): string
    {
        return self::time($at) . ' · ' . $durationMinutes . ' min';
    }

    /** « jeudi 22/10 à 9h30 » (SMS). */
    public static function slot(\DateTimeImmutable $at): string
    {
        return self::day($at) . ' ' . $at->format('d/m') . ' à ' . self::time($at);
    }

    /** « jeudi 22 octobre à 9h30 » (e-mail subject). */
    public static function subjectDate(\DateTimeImmutable $at): string
    {
        return self::day($at) . ' ' . $at->format('j') . ' ' . self::MONTHS[(int) $at->format('n')] . ' à ' . self::time($at);
    }

    /** « Jeudi 22 octobre 2026 ». */
    public static function date(\DateTimeImmutable $at): string
    {
        return ucfirst(self::day($at)) . ' ' . $at->format('j') . ' ' . self::MONTHS[(int) $at->format('n')] . ' ' . $at->format('Y');
    }

    /** « Jeu. 22 oct. · 9h30 » (old and new slots of a change e-mail). */
    public static function shortSlot(\DateTimeImmutable $at): string
    {
        return self::SHORT_DAYS[(int) $at->format('N')] . ' ' . $at->format('j') . ' ' . self::SHORT_MONTH[(int) $at->format('n')] . ' · ' . self::time($at);
    }

    /**
     * When the appointment is, seen from now (both in the clinic's time
     * zone): « aujourd'hui à 9h30 », « demain à 9h30 », else « jeudi 22/10 à 9h30 ».
     */
    public static function when(\DateTimeImmutable $at, \DateTimeImmutable $now): string
    {
        return match ($at->format('Y-m-d')) {
            $now->format('Y-m-d')                   => "aujourd'hui à " . self::time($at),
            $now->modify('+1 day')->format('Y-m-d') => 'demain à ' . self::time($at),
            default                                 => self::slot($at),
        };
    }

    /**
     * A member as the bell names them: « Julie M. » from « Julie Martin »,
     * « Claire L. » from « Dr Claire Lambert » (or « Dr. », « Docteur »,
     * « Pr », « Professeur »); a single name (« Dr Rousseau ») is kept whole.
     */
    public static function shortName(string $printedName): string
    {
        $words = preg_split('/\s+/u', $printedName, -1, \PREG_SPLIT_NO_EMPTY);
        $words = \is_array($words) ? $words : [];

        $titled = [] !== $words && \in_array(mb_strtolower(rtrim($words[0], '.')), self::TITLES, true);

        if (\count($words) < ($titled ? 3 : 2)) {
            return implode(' ', $words);
        }

        if ($titled) {
            array_shift($words);
        }

        return $words[0] . ' ' . mb_substr((string) end($words), 0, 1) . '.';
    }

    private static function day(\DateTimeImmutable $at): string
    {
        return self::DAYS[(int) $at->format('N')];
    }
}
