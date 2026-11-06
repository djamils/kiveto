<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * E3 — « Renvoyer l'invitation »: a fresh seven-day link.
 */
final class ResendUserInvitationController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/invitation/resend', name: 'backoffice_user_invitation_resend', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $account = $this->account($request, $id);

        if (!$account->isPending()) {
            $this->addFlash('info', 'Ce compte n’attend plus d’invitation.');

            return $this->back($id);
        }

        $sent = $this->users->resendInvitations($account);
        $this->addFlash($sent > 0 ? 'success' : 'info', $sent > 0 ? 'Invitation renvoyée. Le lien est valable 7 jours.' : 'Aucune clinique ne l’attend : rien n’est parti.');

        return $this->back($id);
    }
}
