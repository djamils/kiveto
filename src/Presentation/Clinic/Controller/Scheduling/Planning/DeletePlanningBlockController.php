<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Planning;

use App\Context\Scheduling\Application\Command\DeletePlanningBlock\DeletePlanningBlock;
use App\Context\Scheduling\Domain\Exception\PlanningBlockNotFoundException;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/scheduling/planning/blocks/{id}', name: 'clinic_planning_block_delete', methods: ['DELETE'])]
final class DeletePlanningBlockController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $this->commandBus->dispatch(new DeletePlanningBlock(
                blockId: $id,
                clinicId: $currentClinicId->toString(),
            ));

            return new JsonResponse(null, 204);
        } catch (PlanningBlockNotFoundException $e) {
            return new JsonResponse(['error' => 'Not found'], 404);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }
    }
}
