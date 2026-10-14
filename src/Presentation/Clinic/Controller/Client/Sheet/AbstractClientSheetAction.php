<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Presentation\Clinic\Controller\Shared\AbstractSheetAction;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Forms of the client sheet come back to the tab they were submitted from.
 */
abstract class AbstractClientSheetAction extends AbstractSheetAction
{
    protected const string CSRF_ID = 'client_sheet';

    protected function backToSheet(string $clientId, string $tab = 'synthese'): RedirectResponse
    {
        return $this->redirectToRoute(
            'clinic_clients_view',
            'synthese' === $tab ? ['id' => $clientId] : ['id' => $clientId, 'tab' => $tab],
            RedirectResponse::HTTP_SEE_OTHER,
        );
    }
}
