<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Révoquer » one of the person's sessions (`session_id`), or « Révoquer
 * toutes les sessions » (none given).
 */
final class RevokeUserSessionsController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/sessions/revoke', name: 'backoffice_user_sessions_revoke', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $account = $this->account($request, $id);
        $session = $request->request->getString('session_id');

        $this->users->revokeSessions($account, '' !== $session ? $session : null);
        $this->addFlash('success', '' !== $session ? 'Session révoquée.' : 'Toutes les sessions sont révoquées.');

        return $this->back($id);
    }
}
