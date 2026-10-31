<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\ListOwnerCareItems;
use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\OwnerCareItem;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\CareItem;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<CareItem>
 */
final readonly class PetCareItemsProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    /**
     * @return list<CareItem>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        $petId    = $uriVariables['petId'] ?? null;
        \assert(\is_string($clinicId) && \is_string($petId));

        $this->guard->require($user->id(), $clinicId);

        try {
            $items = $this->queryBus->ask(new ListOwnerCareItems($user->id(), $clinicId, $petId));
        } catch (PetNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }
        \assert(\is_array($items));

        return array_values(array_map(static function (mixed $item): CareItem {
            \assert($item instanceof OwnerCareItem);

            return CareItem::of($item);
        }, $items));
    }
}
