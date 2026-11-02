<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\OtpResend;
use App\Presentation\Api\Portal\Support\PortalRateLimits;
use App\Presentation\Api\Portal\View\OtpChallengeView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\ResendPortalOtp\ResendPortalOtp;
use App\System\IdentityAccess\Application\Query\GetOtpChallengePhone\GetOtpChallengePhone;
use App\System\IdentityAccess\Domain\Exception\OtpChallengeNotFoundException;
use App\System\IdentityAccess\Domain\Exception\OtpRateLimitedException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Order: the IP quota, then the challenge is resolved (an unknown one
 * answers 404 without touching any other quota), then the phone and
 * challenge quotas, then the new code (a sign-in code to an unknown number
 * spends the unknown-number budget, from the same request context as
 * RequestOtpProcessor).
 *
 * @implements ProcessorInterface<OtpResend, OtpChallengeView>
 */
final readonly class ResendOtpProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private PortalRateLimits $rateLimits,
        private RequestStack $requests,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OtpChallengeView
    {
        $challengeId = $uriVariables['challengeId'] ?? null;
        \assert(\is_string($challengeId));

        $this->rateLimits->consumeIp();

        $phone = $this->queryBus->ask(new GetOtpChallengePhone($challengeId));
        if (!\is_string($phone)) {
            throw PortalProblemMap::problem(PortalProblemMap::OTP_CHALLENGE_NOT_FOUND);
        }

        $this->rateLimits->consumePhone($phone);
        $this->rateLimits->consumeResend($challengeId);

        try {
            $issued = $this->commandBus->dispatch(new ResendPortalOtp($challengeId, $data->channel, RequestOtpProcessor::requestContext($this->requests)));
        } catch (OtpChallengeNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::OTP_CHALLENGE_NOT_FOUND);
        } catch (OtpRateLimitedException $exception) {
            $wait = max(1, $exception->availableAt->getTimestamp() - time());

            throw PortalProblemMap::problem(PortalProblemMap::OTP_RATE_LIMITED, ['Retry-After' => (string) $wait]);
        }

        \assert($issued instanceof OtpChallengeIssued);

        return OtpChallengeView::of($issued);
    }
}
