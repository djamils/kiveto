<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Réactiver le compte » of a disabled account.
 */
final class EnableUserController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/enable', name: 'backoffice_user_enable', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $this->users->enable($this->account($request, $id));
        $this->addFlash('success', 'Compte réactivé.');

        return $this->back($id);
    }
}
