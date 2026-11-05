<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\RemoveTypedVital\RemoveTypedVital;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\Security\RequiresClinicPermission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[RequiresClinicPermission(ClinicPermissionGuard::RECORD_VITALS)]
final class RemoveTypedVitalController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/typed-vitals/remove', name: 'clinic_consultation_remove_typed_vital', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): RemoveTypedVital => new RemoveTypedVital(
                consultationId: $id,
                clinicId: $clinicId,
                type: $request->request->getString('type'),
            ),
        );
    }
}
