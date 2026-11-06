<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Context\Clinic\Application\Command\Account\SuspendClinic\SuspendClinic;
use App\Context\Clinic\Domain\Account\SuspensionReason;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * C6 — « Suspendre la clinique »: only once its tenant id is typed.
 */
final class SuspendClinicController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ConsoleDirectory $directory,
        private readonly CurrentAdmin $currentAdmin,
        private readonly ConsoleAuditTrail $auditTrail,
    ) {
    }

    #[Route(path: '/clinics/{id}/suspend', name: 'backoffice_clinic_suspend', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(UpdateClinicController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $clinic = $this->directory->clinic($id) ?? throw $this->createNotFoundException('Clinique introuvable.');
        $form   = $request->request;
        $reason = SuspensionReason::tryFrom($form->getString('reason'));

        if (null === $reason || trim($form->getString('confirmation')) !== $clinic->slug) {
            $this->addFlash('error', \sprintf('Choisis un motif et tape « %s » pour confirmer.', $clinic->slug));

            return $this->redirectToRoute('backoffice_clinic_show', ['id' => $id, 'dialog' => 'suspend']);
        }

        $admin   = $this->currentAdmin->get();
        $comment = trim($form->getString('comment'));

        $this->commandBus->dispatch(new SuspendClinic($id, $reason->value, $comment, $admin->id, $admin->name));
        $this->auditTrail->record(
            'clinic.suspended',
            AuditCategory::CLINICS,
            \sprintf('Clinique suspendue · %s', mb_strtolower($reason->label())),
            clinicId: $id,
            clinicName: $clinic->name,
            details: ['Motif' => $reason->label()] + ('' !== $comment ? ['Commentaire' => $comment] : []),
        );
        $this->addFlash('success', 'Clinique suspendue. Ses utilisateurs ne peuvent plus se connecter.');

        return $this->redirectToRoute('backoffice_clinic_show', ['id' => $id]);
    }
}
