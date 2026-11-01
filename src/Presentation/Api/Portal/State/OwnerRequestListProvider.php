<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerPortalRequests\ListOwnerPortalRequests;
use App\Context\ClientPortal\Application\Query\ListOwnerPortalRequests\OwnerPortalRequest;
use App\Presentation\Api\Portal\Resource\OwnerRequestList;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\PortalRequestView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<OwnerRequestList>
 */
final readonly class OwnerRequestListProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): OwnerRequestList
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);

        $query = QueryString::of($context);
        $page  = $this->queryBus->ask(new ListOwnerPortalRequests($user->id(), $clinicId, $query->string('cursor'), $query->int('limit')));
        \assert($page instanceof CursorPage);

        return new OwnerRequestList(
            array_map(static function (mixed $request): PortalRequestView {
                \assert($request instanceof OwnerPortalRequest);

                return PortalRequestView::of($request);
            }, $page->items),
            $page->nextCursor,
        );
    }
}
