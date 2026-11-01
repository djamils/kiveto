<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\RequestAccountDeletion\RequestAccountDeletion;
use App\Context\ClientPortal\Application\Port\DeletionCodeCheck;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\DeletionRequest;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Exception\PortalAccountUnavailableException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * A wrong code changes nothing but its attempt; a right one disables the
 * account before the 202 is sent (the erasure follows asynchronously).
 *
 * @implements ProcessorInterface<DeletionRequest, null>
 */
final readonly class RequestAccountDeletionProcessor implements ProcessorInterface
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

        try {
            $check = $this->commandBus->dispatch(new RequestAccountDeletion($user->id(), $data->challengeId, $data->code));
        } catch (PortalAccountUnavailableException) {
            throw PortalProblemMap::problem(PortalProblemMap::UNAUTHORIZED);
        }

        \assert($check instanceof DeletionCodeCheck);

        return match ($check) {
            DeletionCodeCheck::VERIFIED              => null,
            DeletionCodeCheck::OTP_INVALID           => throw PortalProblemMap::problem(PortalProblemMap::OTP_INVALID),
            DeletionCodeCheck::OTP_EXPIRED           => throw PortalProblemMap::problem(PortalProblemMap::OTP_EXPIRED),
            DeletionCodeCheck::OTP_ATTEMPTS_EXCEEDED => throw PortalProblemMap::problem(PortalProblemMap::OTP_ATTEMPTS_EXCEEDED),
        };
    }
}
