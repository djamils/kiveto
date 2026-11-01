<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Presentation\Clinic\Controller\Shared\AbstractSheetAction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What « Confirmer » and « Refuser » a « Rattachement » share: the CSRF
 * token, the files staff were shown, and the way back (PRG with a flash).
 */
abstract class AbstractClientLinkAnswer extends AbstractSheetAction
{
    protected const string CSRF_ID = 'portal_link_request';

    /**
     * The client files listed on the page (`client_ids[]`), valid ids only.
     *
     * @return list<string>
     */
    protected function clientIds(Request $request): array
    {
        $ids = $request->request->all('client_ids');

        return array_values(array_filter($ids, static fn (mixed $id): bool => \is_string($id) && 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)));
    }

    /** Back on the request while something is still pending there, else on the queue. */
    protected function back(string $userId, bool $stillPending): Response
    {
        return $stillPending
            ? $this->redirectToRoute('clinic_portal_link_requests_show', ['userId' => $userId], Response::HTTP_SEE_OTHER)
            : $this->redirectToRoute('clinic_portal_requests', [], Response::HTTP_SEE_OTHER);
    }
}
