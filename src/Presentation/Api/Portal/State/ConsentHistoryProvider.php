<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\ListPortalConsentHistory\ListPortalConsentHistory;
use App\Context\ClientPortal\Application\Query\ListPortalConsentHistory\PortalConsentHistoryItem;
use App\Context\ClientPortal\Domain\Exception\ClinicNotLinkedException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\ConsentHistory;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\ConsentHistoryItemView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * An unreadable cursor leaves as `400 cursor_invalid` (InvalidCursorListener).
 *
 * @implements ProviderInterface<ConsentHistory>
 */
final readonly class ConsentHistoryProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ConsentHistory
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);

        $query = QueryString::of($context);

        try {
            $page = $this->queryBus->ask(new ListPortalConsentHistory($user->id(), $clinicId, $query->string('cursor'), $query->int('limit')));
        } catch (ClinicNotLinkedException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }
        \assert($page instanceof CursorPage);

        return new ConsentHistory(
            array_map(static function (mixed $item): ConsentHistoryItemView {
                \assert($item instanceof PortalConsentHistoryItem);

                return ConsentHistoryItemView::of($item);
            }, $page->items),
            $page->nextCursor,
        );
    }
}
