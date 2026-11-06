<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * E3 — « Annuler l'invitation »: the links stop working, the account is off.
 */
final class CancelUserInvitationController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/invitation/cancel', name: 'backoffice_user_invitation_cancel', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $account = $this->account($request, $id);

        if (!$account->isPending()) {
            $this->addFlash('info', 'Ce compte n’attend plus d’invitation.');

            return $this->back($id);
        }

        $this->users->cancelInvitation($account);
        $this->addFlash('success', 'Invitation annulée. Le lien ne fonctionne plus.');

        return $this->back($id);
    }
}
