<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Directory;

use App\Context\Client\Application\Query\ListCommunesForPostalCode\ListCommunesForPostalCode;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The communes a postal code (`cp`) serves, as JSON; `null` when the official
 * list cannot be reached, so the window lets the commune be typed instead.
 */
#[Route('/directory/communes', name: 'clinic_directory_communes', methods: ['GET'])]
final class ListCommunesController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $communes = $this->queryBus->ask(new ListCommunesForPostalCode($request->query->getString('cp')));

        return new JsonResponse(['communes' => \is_array($communes) ? array_values(array_filter($communes, \is_string(...))) : null]);
    }
}
