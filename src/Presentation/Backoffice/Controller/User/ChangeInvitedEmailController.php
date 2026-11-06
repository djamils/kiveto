<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use App\Presentation\Backoffice\User\UserActionRefusedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * E3 — « Modifier l'e-mail » of an invitation, sent again to the new address.
 */
final class ChangeInvitedEmailController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/invitation/email', name: 'backoffice_user_invitation_email', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $account = $this->account($request, $id);

        // The window exists only while the invitation waits.
        if (!$account->isPending()) {
            $this->addFlash('error', 'L’e-mail ne se corrige que tant que l’invitation n’est pas acceptée.');

            return $this->back($id);
        }

        try {
            $this->users->changeEmail($account, $request->request->getString('email'));
        } catch (UserActionRefusedException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->back($id, 'email');
        }

        $this->addFlash('success', 'E-mail corrigé. L’invitation est repartie à la nouvelle adresse.');

        return $this->back($id);
    }
}
