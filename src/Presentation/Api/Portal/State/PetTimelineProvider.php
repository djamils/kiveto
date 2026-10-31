<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\ListPetTimeline\ListPetTimeline;
use App\Context\ClientPortal\Application\Query\ListPetTimeline\TimelineEntry;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\PetTimeline;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\TimelineEntryView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * An unreadable cursor leaves as `400 cursor_invalid` (InvalidCursorListener).
 *
 * @implements ProviderInterface<PetTimeline>
 */
final readonly class PetTimelineProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PetTimeline
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        $petId    = $uriVariables['petId'] ?? null;
        \assert(\is_string($clinicId) && \is_string($petId));

        $this->guard->require($user->id(), $clinicId);

        $query = QueryString::of($context);

        try {
            $page = $this->queryBus->ask(new ListPetTimeline($user->id(), $clinicId, $petId, $query->string('cursor'), $query->int('limit')));
        } catch (PetNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }
        \assert($page instanceof CursorPage);

        return new PetTimeline(
            array_map(static function (mixed $entry): TimelineEntryView {
                \assert($entry instanceof TimelineEntry);

                return TimelineEntryView::of($entry);
            }, $page->items),
            $page->nextCursor,
        );
    }
}
