<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Act\ArchiveAct\ArchiveAct;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** « Archiver » an act — from its sheet or the bulk bar; it is no longer offered. */
#[Route(path: '/catalog/acts/{actId}/archive', name: 'clinic_catalogue_act_archive', methods: ['POST'])]
final readonly class ArchiveActController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $actId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        try {
            $this->commandBus->dispatch(new ArchiveAct($actId, $this->endpoint->clinicId()));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
