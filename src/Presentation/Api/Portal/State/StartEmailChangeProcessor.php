<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\EmailChange;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\View\EmailVerificationChallengeView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\EmailChangeStarted;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\StartPortalEmailChange;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\IdentityAccess\Application\Exception\PortalAccountUnavailableException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<EmailChange, EmailVerificationChallengeView>
 */
final readonly class StartEmailChangeProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EmailVerificationChallengeView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);

        try {
            $started = $this->commandBus->dispatch(new StartPortalEmailChange($user->id(), $data->challengeId, $data->code, $data->email));
        } catch (PortalAccountUnavailableException) {
            throw PortalProblemMap::problem(PortalProblemMap::UNAUTHORIZED);
        } catch (\InvalidArgumentException) {
            // An address the constraint lets through but the account cannot store.
            throw PortalProblemMap::violation('email', 'Cette adresse e-mail n\'est pas valide.', $data->email);
        }

        \assert($started instanceof EmailChangeStarted);

        return match ($started->result) {
            ActionOtpResult::VERIFIED              => new EmailVerificationChallengeView((string) $started->verificationChallengeId),
            ActionOtpResult::OTP_INVALID           => throw PortalProblemMap::problem(PortalProblemMap::OTP_INVALID),
            ActionOtpResult::OTP_EXPIRED           => throw PortalProblemMap::problem(PortalProblemMap::OTP_EXPIRED),
            ActionOtpResult::OTP_ATTEMPTS_EXCEEDED => throw PortalProblemMap::problem(PortalProblemMap::OTP_ATTEMPTS_EXCEEDED),
        };
    }
}
