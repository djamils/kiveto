<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\AcceptLinkSuggestion\AcceptLinkSuggestion;
use App\Context\ClientPortal\Domain\Exception\LinkSuggestionNotFoundException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\View\LinkSuggestionAcceptedView;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<null, LinkSuggestionAcceptedView>
 */
final readonly class AcceptLinkSuggestionProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LinkSuggestionAcceptedView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $suggestionId = $uriVariables['suggestionId'] ?? null;
        \assert(\is_string($suggestionId));

        try {
            $status = $this->commandBus->dispatch(new AcceptLinkSuggestion($user->id(), $suggestionId));
        } catch (LinkSuggestionNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }
        \assert(\is_string($status));

        return new LinkSuggestionAcceptedView($status);
    }
}
