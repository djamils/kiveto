<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Billing;

use App\Context\Billing\Application\Query\GetInvoiceDetails\TransmissionView;

/**
 * The « Facture électronique » pill of a professional document — where its
 * transmission to the approved platform stands — and, when someone has to act,
 * the « Action requise » banner of its sheet with the next step to take.
 *
 * Tones: `wait` (on its way), `sync` (handed to the platform), `ok` (approved
 * or paid), `info` (nothing to do), `alert` (stuck or refused). A private
 * (B2C) document has no transmission, hence no badge.
 */
final class TransmissionBadge
{
    public const string WAIT  = 'wait';
    public const string SYNC  = 'sync';
    public const string OK    = 'ok';
    public const string INFO  = 'info';
    public const string ALERT = 'alert';

    private const string NEXT_REISSUE = 'Créez un avoir puis une nouvelle facture.';
    private const string NEXT_CLIENT  = 'Contactez le client.';
    private const string NEXT_SUPPORT = 'Contactez le support Kiveto.';

    /** Lifecycle statuses that read as a happy ending. */
    private const array OK_CODES = [205, 206, 209, 211, 212, 214];

    /** Lifecycle statuses that stop the document, with what to do about it. */
    private const array NEXT_BY_CODE = [
        207 => self::NEXT_CLIENT,
        208 => self::NEXT_CLIENT,
        210 => self::NEXT_REISSUE,
        213 => self::NEXT_REISSUE,
        220 => self::NEXT_REISSUE,
        221 => self::NEXT_SUPPORT,
        501 => self::NEXT_SUPPORT,
    ];

    private const array BLOCK_REASONS = [
        'RENDERING_FAILED'              => 'Erreur de génération',
        'ORGANISATION_MISMATCH'         => 'SIREN de la connexion incorrect',
        'PLATFORM_REFUSED'              => 'Refusé par la plateforme',
        'SUBMISSION_ATTEMPTS_EXHAUSTED' => 'Envoi impossible',
        'DUPLICATE_FLOW'                => 'Double dépôt détecté',
    ];

    /** States that come before any lifecycle status. */
    private const array EARLY_STATES = [
        'requested'          => ['Transmission demandée', self::WAIT],
        'request_failed'     => ['Transmission non demandée', self::ALERT],
        'pending_connection' => ['Plateforme non connectée', self::WAIT],
        'queued'             => ['En attente d\'envoi', self::WAIT],
        'submitting'         => ['En attente d\'envoi', self::WAIT],
        'not_required'       => ['Non transmis — facture d\'origine annulée', self::INFO],
    ];

    /**
     * @return array{text: string, tone: string, action?: array{title: string, next: string, reason: ?string}}|null
     */
    public static function of(?TransmissionView $view): ?array
    {
        if (null === $view) {
            return null;
        }

        if (isset(self::EARLY_STATES[$view->state])) {
            [$text, $tone] = self::EARLY_STATES[$view->state];

            return self::badge($view, $text, $tone, 'request_failed' === $view->state ? self::NEXT_SUPPORT : null);
        }

        if ('blocked' === $view->state) {
            return self::badge($view, self::BLOCK_REASONS[$view->blockReason ?? ''] ?? 'Envoi bloqué', self::ALERT, self::NEXT_SUPPORT);
        }

        if (null !== $view->statusCode) {
            return self::status($view, $view->statusCode);
        }

        return self::badge($view, 'Transmise', self::SYNC, null);
    }

    /**
     * @return array{text: string, tone: string, action?: array{title: string, next: string, reason: ?string}}
     */
    private static function status(TransmissionView $view, int $code): array
    {
        // Deposited for a buyer without a platform: the deposit is the end of the road.
        if (200 === $code && 'NON_TRANSMISE' === $view->reasonCode) {
            return ['text' => 'Déposée — client sans plateforme', 'tone' => self::INFO];
        }

        if (501 === $code) {
            return self::badge($view, 'Irrecevable', self::ALERT, self::NEXT_SUPPORT);
        }

        $next = self::NEXT_BY_CODE[$code] ?? null;
        $tone = match (true) {
            \in_array($code, self::OK_CODES, true)  => self::OK,
            null !== $next || $view->requiresAction => self::ALERT,
            default                                 => self::WAIT,
        };

        return self::badge($view, $view->statusLabel ?? \sprintf('Statut %d', $code), $tone, $next);
    }

    /**
     * @return array{text: string, tone: string, action?: array{title: string, next: string, reason: ?string}}
     */
    private static function badge(TransmissionView $view, string $text, string $tone, ?string $next): array
    {
        $badge = ['text' => $text, 'tone' => $tone];

        if (null === $next && !$view->requiresAction) {
            return $badge;
        }

        $reason = null !== $view->reason && '' !== mb_trim($view->reason) ? $view->reason : null;

        return $badge + ['action' => ['title' => 'Action requise', 'next' => $next ?? self::NEXT_SUPPORT, 'reason' => $reason]];
    }
}
