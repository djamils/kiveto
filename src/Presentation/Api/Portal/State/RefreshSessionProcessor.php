<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\SessionRefresh;
use App\Presentation\Api\Portal\View\SessionTokensView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\RefreshPortalSession\RefreshPortalSession;
use App\System\IdentityAccess\Application\Command\RefreshPortalSession\RefreshResult;

/**
 * Every refusal is the same `401 refresh_revoked`: the app signs in again.
 *
 * @implements ProcessorInterface<SessionRefresh, SessionTokensView>
 */
final readonly class RefreshSessionProcessor implements ProcessorInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SessionTokensView
    {
        $result = $this->commandBus->dispatch(new RefreshPortalSession($data->refreshToken));
        \assert($result instanceof RefreshResult);

        if (null === $result->tokens) {
            throw PortalProblemMap::problem(PortalProblemMap::REFRESH_REVOKED);
        }

        return SessionTokensView::of($result->tokens);
    }
}
