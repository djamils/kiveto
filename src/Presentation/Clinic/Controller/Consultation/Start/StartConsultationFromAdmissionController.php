<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Start;

use App\Context\Consultation\Application\Command\StartConsultationFromAdmission\StartConsultationFromAdmission;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(
    '/admission/{admissionId}/start-consultation',
    name: 'clinic_consultation_start_from_admission',
    methods: ['POST'],
)]
final class StartConsultationFromAdmissionController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, string $admissionId): Response
    {
        $token = $request->request->getString('_token');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('consultation_start', $token))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        try {
            $consultationId = $this->commandBus->dispatch(new StartConsultationFromAdmission(
                admissionId: $admissionId,
                startedByUserId: $user->id(),
            ));

            \assert(\is_string($consultationId));

            return $this->redirectToRoute('clinic_consultation_details', ['id' => $consultationId]);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('clinic_admission_queue');
        }
    }
}
