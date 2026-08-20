<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\Record;

use App\Context\Consultation\Application\Command\RecordVitals\RecordVitals;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RecordVitalsController extends AbstractController
{
    public function __construct(
        private readonly CockpitEndpoint $endpoint,
    ) {
    }

    #[Route('/clinic/consultations/{id}/vitals', name: 'clinic_consultation_record_vitals', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        return $this->endpoint->run(
            $request,
            $id,
            static fn (string $clinicId, string $userId): RecordVitals => new RecordVitals(
                consultationId: $id,
                clinicId: $clinicId,
                weightKg: self::optionalFloat($request, 'weightKg'),
                temperatureC: self::optionalFloat($request, 'temperatureC'),
            ),
        );
    }

    private static function optionalFloat(Request $request, string $key): ?float
    {
        $raw = trim($request->request->getString($key));

        return '' !== $raw ? (float) $raw : null;
    }
}
