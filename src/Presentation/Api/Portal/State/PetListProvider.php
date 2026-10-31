<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\ListOwnerPets;
use App\Context\ClientPortal\Application\Query\ListOwnerPets\OwnerPet;
use App\Presentation\Api\Portal\Resource\PetSummary;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<PetSummary>
 */
final readonly class PetListProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    /**
     * @return list<PetSummary>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);

        $pets = $this->queryBus->ask(new ListOwnerPets($user->id(), $clinicId));
        \assert(\is_array($pets));

        return array_values(array_map(static function (mixed $pet): PetSummary {
            \assert($pet instanceof OwnerPet);

            return PetSummary::of($pet);
        }, $pets));
    }
}
