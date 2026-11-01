<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests\ListOwnerBookingRequests;
use App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests\OwnerBookingRequest;
use App\Presentation\Api\Portal\Resource\BookingRequests;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\BookingRequestView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Account-scoped: no clinic guard (S7d).
 *
 * @implements ProviderInterface<BookingRequests>
 */
final readonly class BookingRequestsProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BookingRequests
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);

        $query = QueryString::of($context);
        $page  = $this->queryBus->ask(new ListOwnerBookingRequests($user->id(), $query->string('cursor'), $query->int('limit')));
        \assert($page instanceof CursorPage);

        return new BookingRequests(
            array_map(static function (mixed $request): BookingRequestView {
                \assert($request instanceof OwnerBookingRequest);

                return BookingRequestView::of($request);
            }, $page->items),
            $page->nextCursor,
        );
    }
}
