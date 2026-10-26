<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Shared;

use App\Shared\Infrastructure\Http\ReturnToResolver;

/**
 * The « Retour à … » button of a page opened from somewhere else (the client
 * sheet or the settings opened from a refused checkout): only for a target
 * ReturnToResolver accepts, never for a raw URL. The target itself (`raw`)
 * is kept so that the page's forms carry it along.
 */
final readonly class ReturnLink
{
    private const array LABELS = [
        'clinic_checkout'          => 'Retour à l\'encaissement',
        'clinic_invoice_details'   => 'Retour à la facturation',
        'clinic_invoices'          => 'Retour à la facturation',
        'clinic_clients_list'      => 'Retour aux clients',
        'clinic_scheduling_agenda' => 'Retour à l\'agenda',
    ];

    public function __construct(
        private ReturnToResolver $resolver,
    ) {
    }

    /**
     * @return array{raw: string, url: string, label: string}|null
     */
    public function from(?string $raw): ?array
    {
        $route = $this->resolver->routeOf($raw);
        $url   = $this->resolver->resolveOrNull($raw);

        if (null === $route || null === $url || null === $raw) {
            return null;
        }

        return ['raw' => $raw, 'url' => $url, 'label' => self::LABELS[$route] ?? 'Retour'];
    }
}
