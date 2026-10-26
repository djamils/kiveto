<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Billing;

use App\Context\Billing\Application\Command\SendInvoiceToClient\InvoiceDispatchOutcome;

/**
 * The toast after « Envoyer » or « Relancer »: what was queued, and what
 * was left out and why (« 10 factures en cours d'envoi · 2 non envoyées :
 * brouillon »).
 */
final class DispatchToast
{
    public static function sent(InvoiceDispatchOutcome $outcome): string
    {
        $n = \count($outcome->queued);

        return self::with(
            match ($n) {
                0       => 'Aucune facture envoyée',
                1       => 'Facture en cours d\'envoi par e-mail',
                default => \sprintf('%d factures en cours d\'envoi par e-mail', $n),
            },
            $outcome,
            'non envoyée',
        );
    }

    public static function reminded(InvoiceDispatchOutcome $outcome): string
    {
        $n = \count($outcome->queued);

        return self::with(
            match ($n) {
                0       => 'Aucune facture relancée',
                1       => 'Relance en cours d\'envoi',
                default => \sprintf('%d relances en cours d\'envoi', $n),
            },
            $outcome,
            'non relancée',
        );
    }

    private static function with(string $head, InvoiceDispatchOutcome $outcome, string $left): string
    {
        $n = \count($outcome->skipped);

        if (0 === $n) {
            return $head;
        }

        $reasons = implode(', ', array_values(array_unique($outcome->skipped)));

        return \sprintf('%s · %d %s%s : %s', $head, $n, $left, $n > 1 ? 's' : '', $reasons);
    }
}
