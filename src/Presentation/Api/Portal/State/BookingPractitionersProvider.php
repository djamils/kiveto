<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\Scheduling\Application\Query\ListBookablePractitioners\BookablePractitioner;
use App\Context\Scheduling\Application\Query\ListBookablePractitioners\ListBookablePractitioners;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\OnlineBookingGate;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\PractitionerView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<PractitionerView>
 */
final readonly class BookingPractitionersProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private OnlineBookingGate $booking,
        private Security $security,
    ) {
    }

    /**
     * @return list<PractitionerView>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);
        $this->booking->require($clinicId);

        $practitioners = $this->queryBus->ask(new ListBookablePractitioners($clinicId, (string) QueryString::of($context)->string('motifId')));

        if (!\is_array($practitioners)) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }

        return array_values(array_map(static function (mixed $practitioner): PractitionerView {
            \assert($practitioner instanceof BookablePractitioner);

            return PractitionerView::of($practitioner);
        }, $practitioners));
    }
}
