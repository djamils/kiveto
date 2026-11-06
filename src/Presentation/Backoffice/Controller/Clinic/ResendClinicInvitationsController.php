<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Presentation\Backoffice\Clinic\ConsoleStaffInviter;
use App\Presentation\Backoffice\ViewModel\Clinic\ClinicSheetBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Relancer » (the clinics' to-do list, the onboarding checklist): a new
 * 7-day link for every member still waiting.
 */
final class ResendClinicInvitationsController extends AbstractController
{
    public function __construct(
        private readonly ConsoleStaffInviter $inviter,
        private readonly ClinicSheetBuilder $sheets,
    ) {
    }

    #[Route(path: '/clinics/{id}/invitations/resend', name: 'backoffice_clinic_invitations_resend', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(UpdateClinicController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $sent = $this->inviter->resend($id, $this->sheets->waiting($id));
        $this->addFlash($sent > 0 ? 'success' : 'info', $sent > 0 ? \sprintf('%d invitation%s relancée%s.', $sent, $sent > 1 ? 's' : '', $sent > 1 ? 's' : '') : 'Aucune invitation en attente.');

        return 'list' === $request->request->getString('back')
            ? $this->redirectToRoute('backoffice_clinics')
            : $this->redirectToRoute('backoffice_clinic_show', ['id' => $id]);
    }
}
