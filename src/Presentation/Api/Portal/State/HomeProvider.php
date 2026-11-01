<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\GetOwnerHome;
use App\Context\ClientPortal\Application\Query\GetOwnerHome\OwnerHome;
use App\Presentation\Api\Portal\Resource\Home;
use App\Presentation\Api\Portal\Resource\PetSummary;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\AppointmentView;
use App\Presentation\Api\Portal\View\DocumentView;
use App\Presentation\Api\Portal\View\HomeCareItemView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<Home>
 */
final readonly class HomeProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Home
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);

        $home = $this->queryBus->ask(new GetOwnerHome($user->id(), $clinicId));
        \assert($home instanceof OwnerHome);

        return new Home(
            null === $home->nextAppointment ? null : AppointmentView::of($home->nextAppointment),
            array_map(PetSummary::of(...), $home->pets),
            array_map(HomeCareItemView::of(...), $home->careItems),
            array_map(DocumentView::of(...), $home->latestDocuments),
        );
    }
}
