<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Port\AppointmentScope;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\ListOwnerAppointments;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\PortalAppointment;
use App\Presentation\Api\Portal\Resource\AppointmentList;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\AppointmentView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<AppointmentList>
 */
final readonly class AppointmentListProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AppointmentList
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);

        $query = QueryString::of($context);
        $page  = $this->queryBus->ask(new ListOwnerAppointments(
            $user->id(),
            $clinicId,
            AppointmentScope::tryFrom((string) $query->string('scope')) ?? AppointmentScope::UPCOMING,
            $query->string('cursor'),
            $query->int('limit'),
        ));
        \assert($page instanceof CursorPage);

        return new AppointmentList(
            array_map(static function (mixed $appointment): AppointmentView {
                \assert($appointment instanceof PortalAppointment);

                return AppointmentView::of($appointment);
            }, $page->items),
            $page->nextCursor,
        );
    }
}
