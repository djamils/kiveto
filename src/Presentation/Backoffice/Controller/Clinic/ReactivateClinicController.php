<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Context\Clinic\Application\Command\Account\ReactivateClinic\ReactivateClinic;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * C3 — « Réactiver la clinique ».
 */
final class ReactivateClinicController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ConsoleDirectory $directory,
        private readonly ConsoleAuditTrail $auditTrail,
    ) {
    }

    #[Route(path: '/clinics/{id}/reactivate', name: 'backoffice_clinic_reactivate', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(UpdateClinicController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $clinic = $this->directory->clinic($id) ?? throw $this->createNotFoundException('Clinique introuvable.');

        $this->commandBus->dispatch(new ReactivateClinic($id));
        $this->auditTrail->record('clinic.reactivated', AuditCategory::CLINICS, 'Clinique réactivée', clinicId: $id, clinicName: $clinic->name);
        $this->addFlash('success', 'Clinique réactivée. Ses utilisateurs retrouvent l’accès.');

        return $this->redirectToRoute('backoffice_clinic_show', ['id' => $id]);
    }
}
