<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Presentation\Backoffice\ViewModel\Clinic\ClinicSheetBuilder;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\StartSupportSession\StartSupportSession;
use App\System\IdentityAccess\Application\Command\StartSupportSession\SupportHandoff;
use App\System\IdentityAccess\Domain\Support\SupportMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * D1 — « Démarrer la session »: the console hands the browser over to the
 * clinic host, signed in as the person chosen. Audited.
 */
final class StartSupportSessionController extends AbstractController
{
    public function __construct(
        private readonly ClinicSheetBuilder $sheets,
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentAdmin $currentAdmin,
        private readonly ConsoleAuditTrail $auditTrail,
    ) {
    }

    #[Route(path: '/clinics/{id}/support', name: 'backoffice_support_start', requirements: ['id' => ShowClinicController::UUID], methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(UpdateClinicController::CSRF_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $support = $this->sheets->support($id) ?? throw $this->createNotFoundException('Clinique introuvable.');
        $form    = $request->request;
        $userId  = $form->getString('user');
        $person  = $support['members'][$userId] ?? null;
        $mode    = SupportMode::tryFrom($form->getString('mode')) ?? SupportMode::READ;
        $minutes = $form->getInt('minutes');

        $refusal = match (true) {
            $support['suspended']                                  => 'La clinique est suspendue : pas de session support.',
            null === $person                                       => 'Choisis la personne en tant que qui te connecter.',
            SupportMode::WRITE === $mode && !$support['can_write'] => 'L’écriture en session support n’est pas activée pour cette clinique.',
            default                                                => null,
        };

        if (null !== $refusal || null === $person) {
            $this->addFlash('error', $refusal ?? 'Choisis la personne en tant que qui te connecter.');

            return $this->redirectToRoute('backoffice_clinic_show', ['id' => $id, 'support' => 1]);
        }

        $admin = $this->currentAdmin->get();

        try {
            $handoff = $this->commandBus->dispatch(new StartSupportSession($id, $userId, $admin->id, $admin->name, $form->getString('reason'), $mode->value, $minutes));
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('backoffice_clinic_show', ['id' => $id, 'support' => 1]);
        }

        \assert($handoff instanceof SupportHandoff);

        $this->auditTrail->record(
            'support.session_started',
            AuditCategory::SUPPORT,
            \sprintf('Session support démarrée en tant que %s', $person['name']),
            clinicId: $id,
            clinicName: $support['clinicName'],
            targetUserId: $userId,
            details: ['Motif' => trim($form->getString('reason')), 'Mode' => $mode->label(), 'Durée' => $minutes . ' min'],
        );

        return $this->redirect($handoff->url);
    }
}
