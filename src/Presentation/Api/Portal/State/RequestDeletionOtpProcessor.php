<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalRateLimits;
use App\Presentation\Api\Portal\View\OtpChallengeView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Command\RequestPortalActionOtp\RequestPortalActionOtp;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Exception\PortalAccountUnavailableException;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\GetPortalAccount;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\PortalAccount;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The code goes to the account's phone and spends the same per-phone and
 * per-IP quotas as a sign-in code (P6).
 *
 * @implements ProcessorInterface<null, OtpChallengeView>
 */
final readonly class RequestDeletionOtpProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private PortalRateLimits $rateLimits,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OtpChallengeView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);

        $account = $this->queryBus->ask(new GetPortalAccount($user->id()));
        if (!$account instanceof PortalAccount) {
            throw PortalProblemMap::problem(PortalProblemMap::UNAUTHORIZED);
        }

        $this->rateLimits->consumePhone($account->phone);
        $this->rateLimits->consumeIp();

        try {
            $issued = $this->commandBus->dispatch(new RequestPortalActionOtp($user->id(), 'account_deletion'));
        } catch (PortalAccountUnavailableException) {
            throw PortalProblemMap::problem(PortalProblemMap::UNAUTHORIZED);
        }

        \assert($issued instanceof OtpChallengeIssued);

        return OtpChallengeView::of($issued);
    }
}
