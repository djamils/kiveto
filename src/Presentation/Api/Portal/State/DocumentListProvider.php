<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\ListPublishedDocuments;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\PortalDocument;
use App\Presentation\Api\Portal\Resource\DocumentList;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\DocumentView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<DocumentList>
 */
final readonly class DocumentListProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DocumentList
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);

        $query = QueryString::of($context);
        $page  = $this->queryBus->ask(new ListPublishedDocuments(
            $user->id(),
            $clinicId,
            $query->string('type'),
            $query->string('petId'),
            $query->string('cursor'),
            $query->int('limit'),
        ));
        \assert($page instanceof CursorPage);

        return new DocumentList(
            array_map(static function (mixed $document): DocumentView {
                \assert($document instanceof PortalDocument);

                return DocumentView::of($document);
            }, $page->items),
            $page->nextCursor,
        );
    }
}
