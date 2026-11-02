<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What « Confirmer » and « Refuser » a « Rattachement » share: the CSRF
 * token, the files staff were shown, and the way back — JSON for the page
 * module (`pending` while something still waits there), else PRG with a
 * flash.
 */
abstract class AbstractClientLinkAnswer extends AbstractPortalRequestAnswer
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
    protected function back(Request $request, string $userId, int $answered, string $done, bool $stillPending): Response
    {
        return $this->reply(
            $request,
            0 !== $answered,
            0 === $answered ? 'Demande déjà traitée.' : $done,
            $stillPending ? 'clinic_portal_link_requests_show' : 'clinic_portal_requests',
            $stillPending ? ['userId' => $userId] : [],
            Response::HTTP_CONFLICT,
            ['pending' => $stillPending],
        );
    }
}
