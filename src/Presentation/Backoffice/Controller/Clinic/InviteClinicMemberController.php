<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Presentation\Backoffice\Clinic\ConsoleStaffInviter;
use App\Presentation\Backoffice\Clinic\InvitationRefusedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * C5 — « Inviter un utilisateur · Envoyer l'invitation ».
 */
final class InviteClinicMemberController extends AbstractController
{
    public function __construct(
        private readonly ConsoleStaffInviter $inviter,
    ) {
    }

    #[Route(path: '/clinics/{id}/invite', name: 'backoffice_clinic_invite', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(UpdateClinicController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $form = $request->request;
        $role = ClinicMemberRole::tryFrom($form->getString('role'));

        try {
            if (null === $role) {
                throw new InvitationRefusedException('Choisis le rôle de la personne.');
            }

            $sent = $this->inviter->invite($id, $form->getString('first_name'), $form->getString('last_name'), $form->getString('email'), $role, $form->getBoolean('administrator'));
        } catch (InvitationRefusedException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('backoffice_clinic_show', ['id' => $id, 'dialog' => 'invite']);
        }

        $this->addFlash('success', $sent ? 'Invitation envoyée. Le lien est valable 7 jours.' : 'Ce compte est déjà actif : la personne rejoint la clinique sans e-mail.');

        return $this->redirectToRoute('backoffice_clinic_show', ['id' => $id]);
    }
}
