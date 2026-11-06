<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Presentation\Backoffice\Controller\Clinic\ShowClinicController;
use App\Presentation\Backoffice\User\UserActionRefusedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Ajouter à une clinique »: the person joins another clinic with a role.
 */
final class AddUserToClinicController extends AbstractUserActionController
{
    #[Route(path: '/users/{id}/memberships', name: 'backoffice_user_membership_add', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $account = $this->account($request, $id);
        $role    = ClinicMemberRole::tryFrom($request->request->getString('role'));

        try {
            if (null === $role) {
                throw new UserActionRefusedException('Choisis un rôle.');
            }

            $sent = $this->users->addToClinic($account, $request->request->getString('clinic'), $role, $request->request->getBoolean('administrator'));
        } catch (UserActionRefusedException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->back($id, 'add');
        }

        $this->addFlash('success', $sent ? 'Ajouté à la clinique. Une invitation est partie.' : 'Ajouté à la clinique.');

        return $this->back($id);
    }
}
