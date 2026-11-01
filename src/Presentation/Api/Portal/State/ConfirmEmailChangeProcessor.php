<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\EmailChangeVerification;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalAccountViews;
use App\Presentation\Api\Portal\View\AccountView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\ConfirmPortalEmailChange\ConfirmPortalEmailChange;
use App\System\IdentityAccess\Application\Command\ConfirmPortalEmailChange\EmailChangeResult;
use App\System\IdentityAccess\Application\Exception\PortalAccountUnavailableException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<EmailChangeVerification, AccountView>
 */
final readonly class ConfirmEmailChangeProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private PortalAccountViews $accounts,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AccountView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);

        try {
            $result = $this->commandBus->dispatch(new ConfirmPortalEmailChange($user->id(), $data->challengeId, $data->code));
        } catch (PortalAccountUnavailableException) {
            throw PortalProblemMap::problem(PortalProblemMap::UNAUTHORIZED);
        }

        \assert($result instanceof EmailChangeResult);

        return match ($result) {
            EmailChangeResult::CONFIRMED             => $this->accounts->of($user->id()),
            EmailChangeResult::OTP_INVALID           => throw PortalProblemMap::problem(PortalProblemMap::OTP_INVALID),
            EmailChangeResult::OTP_EXPIRED           => throw PortalProblemMap::problem(PortalProblemMap::OTP_EXPIRED),
            EmailChangeResult::OTP_ATTEMPTS_EXCEEDED => throw PortalProblemMap::problem(PortalProblemMap::OTP_ATTEMPTS_EXCEEDED),
            EmailChangeResult::EMAIL_TAKEN           => throw PortalProblemMap::problem(PortalProblemMap::EMAIL_TAKEN),
        };
    }
}
