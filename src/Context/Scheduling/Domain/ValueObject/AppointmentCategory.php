<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

/**
 * The category of a booking, read from its free-text reason (there is no
 * motif entity): it drives the agenda chip and the Portal's motif label.
 */
enum AppointmentCategory: string
{
    case CONSULTATION = 'consult';
    case EMERGENCY    = 'urg';
    case VACCINATION  = 'vaccin';
    case FOLLOW_UP    = 'suivi';
    case CHECK_UP     = 'bilan';
    case SURGERY      = 'chir';

    public static function fromReason(?string $reason): self
    {
        $r = mb_strtolower(trim((string) $reason));

        // Most specific first: « Contrôle post-stérilisation » is a follow-up, not a surgery
        return match (true) {
            '' === $r                                                                       => self::CONSULTATION,
            1 === preg_match('/urgen|trauma|avp|intoxication|dyspn/u', $r)                  => self::EMERGENCY,
            1 === preg_match('/vaccin|rappel|primo/u', $r)                                  => self::VACCINATION,
            1 === preg_match('/suivi|contrôle|retrait|post-op/u', $r)                       => self::FOLLOW_UP,
            str_contains($r, 'bilan')                                                       => self::CHECK_UP,
            1 === preg_match('/chirurg|stérilis|castration|détartrage|exérèse|ostéo/u', $r) => self::SURGERY,
            default                                                                         => self::CONSULTATION,
        };
    }
}
