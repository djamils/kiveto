<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Controller;

use App\Context\ClientPortal\Application\Query\GetOwnerPet\GetOwnerPet;
use App\Context\ClientPortal\Application\Query\GetOwnerPet\OwnerPetDetails;
use App\Context\ClientPortal\Application\Service\VaccinationBookletFiles;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\Support\SignedFileUrls;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Ensures the pet's booklet is rendered (or reuses the cached one) once the
 * caller was shown to see the pet, then redirects to a signed URL valid
 * 5 minutes over the clinic, the animal and the booklet's content hash.
 */
final readonly class VaccinationBookletController
{
    public function __construct(
        private PortalClinicGuard $guard,
        private QueryBusInterface $queryBus,
        private VaccinationBookletFiles $booklets,
        private SignedFileUrls $urls,
    ) {
    }

    #[Route('/clinics/{clinicId}/pets/{petId}/vaccination-booklet', name: 'api_portal_vaccination_booklet', methods: ['GET'])]
    public function __invoke(string $clinicId, string $petId, #[CurrentUser] PortalApiUser $user): Response
    {
        try {
            $this->guard->require($user->id(), $clinicId);
        } catch (ApiProblem $problem) {
            return PortalProblemMap::response($problem->getType());
        }

        if (!$this->queryBus->ask(new GetOwnerPet($user->id(), $clinicId, $petId)) instanceof OwnerPetDetails) {
            return PortalProblemMap::response(PortalProblemMap::NOT_FOUND);
        }

        try {
            $key = $this->booklets->ensure($clinicId, $petId);
        } catch (PetNotFoundException) {
            return PortalProblemMap::response(PortalProblemMap::NOT_FOUND);
        }

        $response = new RedirectResponse($this->urls->sign('api_portal_booklet_download', [
            'clinicId' => $clinicId,
            'animalId' => $petId,
            'hash'     => SignedBookletDownloadController::hashOf($key, $petId),
        ]));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
