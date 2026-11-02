<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\OtpRequest;
use App\Presentation\Api\Portal\Support\PortalRateLimits;
use App\Presentation\Api\Portal\View\OtpChallengeView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpRequestContext;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtp;
use App\System\IdentityAccess\Application\Exception\PhoneNotSupportedException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<OtpRequest, OtpChallengeView>
 */
final readonly class RequestOtpProcessor implements ProcessorInterface
{
    /** Longer device hints are cut: the header is client-controlled. */
    private const int DEVICE_ID_MAX_LENGTH = 128;

    public function __construct(
        private CommandBusInterface $commandBus,
        private PortalRateLimits $rateLimits,
        private RequestStack $requests,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OtpChallengeView
    {
        $this->rateLimits->consumePhone($data->phone);
        $this->rateLimits->consumeIp();

        try {
            $issued = $this->commandBus->dispatch(new RequestPortalOtp($data->phone, 'login', requestContext: self::requestContext($this->requests)));
        } catch (PhoneNotSupportedException) {
            throw PortalProblemMap::problem(PortalProblemMap::PHONE_NOT_SUPPORTED);
        }

        \assert($issued instanceof OtpChallengeIssued);

        return OtpChallengeView::of($issued);
    }

    /** The client IP (behind the trusted proxies) and the `X-Device-Id` hint (also for a resend). */
    public static function requestContext(RequestStack $requests): OtpRequestContext
    {
        $request = $requests->getMainRequest();
        $device  = $request?->headers->get('X-Device-Id');

        return new OtpRequestContext($request?->getClientIp(), null === $device ? null : mb_substr($device, 0, self::DEVICE_ID_MAX_LENGTH));
    }
}
