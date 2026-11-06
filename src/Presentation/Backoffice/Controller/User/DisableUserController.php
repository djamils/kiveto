<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use App\Presentation\Backoffice\User\ConsoleUserManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * E4 — « Désactiver le compte », with its reason.
 */
final class DisableUserController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/disable', name: 'backoffice_user_disable', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $account = $this->account($request, $id);
        $reason  = $request->request->getString('reason');

        if (!\array_key_exists($reason, ConsoleUserManager::DISABLE_REASONS)) {
            $this->addFlash('error', 'Choisis le motif de la désactivation.');

            return $this->back($id, 'disable');
        }

        $revoked = $this->users->disable($account, $reason);
        $this->addFlash('success', 0 < $revoked ? \sprintf('Compte désactivé. %d session%s révoquée%s.', $revoked, $revoked > 1 ? 's' : '', $revoked > 1 ? 's' : '') : 'Compte désactivé.');

        return $this->back($id);
    }
}
