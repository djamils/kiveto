<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Portal\Resource\SessionLogout;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\RevokePortalSession\RevokePortalSession;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<SessionLogout, null>
 */
final readonly class LogoutProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);

        $this->commandBus->dispatch(new RevokePortalSession($data->refreshToken, $user->id()));

        return null;
    }
}
