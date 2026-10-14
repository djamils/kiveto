<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Package\ArchivePackage\ArchivePackage;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** « Archiver » a package — from its sheet or the bulk bar. */
#[Route(path: '/catalog/packages/{packageId}/archive', name: 'clinic_catalogue_package_archive', methods: ['POST'])]
final readonly class ArchivePackageController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $packageId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        try {
            $this->commandBus->dispatch(new ArchivePackage($packageId, $this->endpoint->clinicId()));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
