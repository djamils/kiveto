<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\User\ConsoleUserManager;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * What every form of a user's sheet shares: the token, the clinic account
 * it acts on, the way back to the sheet.
 */
abstract class AbstractUserActionController extends AbstractController
{
    public function __construct(
        protected readonly ConsoleUserManager $users,
    ) {
    }

    protected function account(Request $request, string $id): AccountSummary
    {
        if (!$this->isCsrfTokenValid(ShowUserController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        return $this->users->account($id) ?? throw $this->createNotFoundException('Utilisateur introuvable.');
    }

    protected function back(string $id, ?string $dialog = null): RedirectResponse
    {
        return $this->redirectToRoute('backoffice_user_show', array_filter(['id' => $id, 'dialog' => $dialog]));
    }
}
