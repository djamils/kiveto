<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Envoyer un lien »: the password reset e-mail of an active account.
 */
final class SendPasswordLinkController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/password-link', name: 'backoffice_user_password_link', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $account = $this->account($request, $id);

        if (!$account->isActive() || null === $account->email) {
            $this->addFlash('error', 'Le compte n’est pas actif : il n’a pas de mot de passe à réinitialiser.');

            return $this->back($id);
        }

        $this->users->sendPasswordLink($account);
        $this->addFlash('success', \sprintf('Un lien de réinitialisation part vers %s (valable 30 minutes).', $account->email));

        return $this->back($id);
    }
}
