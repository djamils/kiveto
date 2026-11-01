<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\ListLinkSuggestions\LinkSuggestion;
use App\Context\ClientPortal\Application\Query\ListLinkSuggestions\ListLinkSuggestions;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\View\LinkSuggestionView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<LinkSuggestionView>
 */
final readonly class LinkSuggestionsProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private Security $security,
    ) {
    }

    /**
     * @return list<LinkSuggestionView>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);

        $suggestions = $this->queryBus->ask(new ListLinkSuggestions($user->id()));
        \assert(\is_array($suggestions));

        return array_values(array_map(static function (mixed $suggestion): LinkSuggestionView {
            \assert($suggestion instanceof LinkSuggestion);

            return LinkSuggestionView::of($suggestion);
        }, $suggestions));
    }
}
