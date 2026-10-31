<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\GetOwnerPet\GetOwnerPet;
use App\Context\ClientPortal\Application\Query\GetOwnerPet\OwnerPetDetails;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\Pet;
use App\Presentation\Api\Portal\Resource\PetSummary;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\ApiDate;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\PetWeightView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<Pet>
 */
final readonly class PetProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Pet
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        $petId    = $uriVariables['petId'] ?? null;
        \assert(\is_string($clinicId) && \is_string($petId));

        $this->guard->require($user->id(), $clinicId);

        $details = $this->queryBus->ask(new GetOwnerPet($user->id(), $clinicId, $petId));

        if (!$details instanceof OwnerPetDetails) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }

        $pet = PetSummary::of($details->pet);

        return new Pet(
            $pet->id,
            $pet->name,
            $pet->species,
            $pet->breed,
            $pet->sex,
            $pet->sterilization,
            $pet->birthDate,
            $pet->initial,
            $pet->lifeStatus,
            null === $details->lastWeight ? null : new PetWeightView($details->lastWeight->weightGrams, ApiDate::date($details->lastWeight->at)),
            $details->chipNumberMasked,
            $details->referentVet,
        );
    }
}
