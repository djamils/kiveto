<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\GetPortalConsents\GetPortalConsents;
use App\Context\ClientPortal\Application\Query\GetPortalConsents\PortalConsents;
use App\Context\ClientPortal\Domain\Exception\ClinicNotLinkedException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\Consents;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<Consents>
 */
final readonly class ConsentsProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Consents
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);

        try {
            $consents = $this->queryBus->ask(new GetPortalConsents($user->id(), $clinicId));
        } catch (ClinicNotLinkedException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }
        \assert($consents instanceof PortalConsents);

        return Consents::of($consents);
    }
}
