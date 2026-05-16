<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Planning;

use App\Context\Scheduling\Application\Command\UpdatePlanningBlock\UpdatePlanningBlock;
use App\Context\Scheduling\Domain\Exception\PlanningBlockNotFoundException;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/scheduling/planning/blocks/{id}', name: 'clinic_planning_block_update', methods: ['PUT'])]
final class UpdatePlanningBlockController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $decoded = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            return new JsonResponse(['error' => 'Invalid JSON payload.'], 400);
        }

        /** @var array<string, mixed> $data */
        $data = $decoded;

        try {
            $this->commandBus->dispatch(new UpdatePlanningBlock(
                blockId: $id,
                clinicId: $currentClinicId->toString(),
                staffUserId: isset($data['vet']) && \is_string($data['vet']) ? $data['vet'] : '',
                type: isset($data['type']) && \is_string($data['type']) ? $data['type'] : '',
                date: isset($data['date']) && \is_string($data['date']) ? $data['date'] : '',
                startTime: isset($data['start']) && \is_string($data['start']) ? $data['start'] : '',
                endTime: isset($data['end']) && \is_string($data['end']) ? $data['end'] : '',
                capacityPerHour: isset($data['capacity']) && \is_int($data['capacity']) ? $data['capacity'] : 0,
                recurrenceFreq: strtoupper(
                    isset($data['recurrence']) && \is_string($data['recurrence']) ? $data['recurrence'] : 'NONE',
                ),
                recurrenceUntil: isset($data['recurrenceUntil'])
                    && \is_string($data['recurrenceUntil'])
                    && '' !== $data['recurrenceUntil']
                    ? $data['recurrenceUntil'] : null,
                note: isset($data['note']) && \is_string($data['note']) && '' !== $data['note']
                    ? $data['note'] : null,
            ));

            return new JsonResponse(null, 204);
        } catch (PlanningBlockNotFoundException $e) {
            return new JsonResponse(['error' => 'Not found'], 404);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }
    }
}
