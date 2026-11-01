<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\ListBookableMotifs\BookableMotif;
use App\Context\ClientPortal\Application\Query\ListBookableMotifs\ListBookableMotifs;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\OnlineBookingGate;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\BookingMotifView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<BookingMotifView>
 */
final readonly class BookingMotifsProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private OnlineBookingGate $booking,
        private Security $security,
    ) {
    }

    /**
     * @return list<BookingMotifView>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);
        $this->booking->require($clinicId);

        try {
            $motifs = $this->queryBus->ask(new ListBookableMotifs($user->id(), $clinicId, QueryString::of($context)->string('petId')));
        } catch (PetNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }
        \assert(\is_array($motifs));

        return array_values(array_map(static function (mixed $motif): BookingMotifView {
            \assert($motif instanceof BookableMotif);

            return BookingMotifView::of($motif);
        }, $motifs));
    }
}
