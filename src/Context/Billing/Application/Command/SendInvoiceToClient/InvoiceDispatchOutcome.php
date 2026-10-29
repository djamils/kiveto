<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\SendInvoiceToClient;

/**
 * What a send or a reminder did with each invoice asked for: queued, or
 * skipped with the reason the toast reports.
 */
final readonly class InvoiceDispatchOutcome
{
    public const string SKIP_UNKNOWN        = 'introuvable';
    public const string SKIP_DRAFT          = 'brouillon';
    public const string SKIP_NOT_ISSUED     = 'annulée';
    public const string SKIP_NO_EMAIL       = 'sans adresse e-mail';
    public const string SKIP_NO_CONTACT     = 'sans coordonnées';
    public const string SKIP_NOTHING_DUE    = 'rien à relancer';
    public const string SKIP_REMINDED_TODAY = 'déjà relancée aujourd\'hui';

    /**
     * @param list<string>          $queued  invoice ids
     * @param array<string, string> $skipped invoice id => reason
     */
    public function __construct(
        public array $queued,
        public array $skipped,
    ) {
    }
}
