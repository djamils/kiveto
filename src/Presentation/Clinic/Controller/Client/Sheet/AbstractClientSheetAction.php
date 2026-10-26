<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Presentation\Clinic\Controller\Shared\AbstractSheetAction;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Forms of the client sheet come back to the sheet; the card they were
 * submitted from is named so the page can flash it as saved. A sheet opened
 * with a `_returnTo` target (see ReturnToResolver) posts it along, and keeps
 * it: its « Retour à … » button survives the save.
 */
abstract class AbstractClientSheetAction extends AbstractSheetAction
{
    protected const string CSRF_ID = 'client_sheet';

    protected function backToSheet(string $clientId, ?string $savedCard = null): RedirectResponse
    {
        $returnTo = $this->currentRequest()?->request->getString('_returnTo') ?? '';

        return $this->redirectToRoute(
            'clinic_clients_view',
            [
                'id' => $clientId,
                ...(null === $savedCard ? [] : ['saved' => $savedCard]),
                // Only the target travels; the sheet checks it against the whitelist before showing it.
                ...('' === $returnTo ? [] : ['_returnTo' => $returnTo]),
            ],
            RedirectResponse::HTTP_SEE_OTHER,
        );
    }

    /**
     * The sheet types French numbers the national way (« 06 12 34 56 78 »);
     * the domain stores them in E.164. A number already in E.164 is kept.
     */
    protected function phone(?string $raw): ?string
    {
        if (null === $raw) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';

        if (1 === preg_match('/^0[1-9]\d{8}$/', $digits)) {
            return '+33' . substr($digits, 1);
        }

        return $digits;
    }
}
