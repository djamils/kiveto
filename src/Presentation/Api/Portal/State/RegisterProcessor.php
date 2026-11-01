<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\CompletePortalRegistration;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\RegistrationOutcome;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\RegistrationResult;
use App\Context\ClientPortal\Domain\Exception\TermsOutdatedException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\Registration;
use App\Presentation\Api\Portal\Support\AcceptanceResponder;
use App\Presentation\Api\Portal\Support\PortalRateLimits;
use App\Presentation\Api\Portal\View\AcceptedInvitationView;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Domain\Exception\PortalAccountExistsException;

/**
 * Runs the registration (one transaction, grant verified by IdentityAccess)
 * and opens a session: the same answer for a first registration and for a
 * replay of its grant.
 *
 * @implements ProcessorInterface<Registration, AcceptedInvitationView>
 */
final readonly class RegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private PortalRateLimits $rateLimits,
        private AcceptanceResponder $sessions,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AcceptedInvitationView
    {
        $this->rateLimits->consumeRegistration();

        try {
            $outcome = $this->commandBus->dispatch(new CompletePortalRegistration(
                registrationToken: $data->registrationToken,
                firstName: $data->firstName,
                lastName: $data->lastName,
                email: $data->email,
                termsVersion: $data->termsVersion,
            ));
        } catch (TermsOutdatedException) {
            throw PortalProblemMap::problem(PortalProblemMap::TERMS_OUTDATED);
        } catch (PortalAccountExistsException) {
            throw PortalProblemMap::problem(PortalProblemMap::ACCOUNT_EXISTS);
        }

        \assert($outcome instanceof RegistrationOutcome);

        return match ($outcome->result) {
            RegistrationResult::REGISTERED,
            RegistrationResult::REPLAYED           => $this->sessions->session($outcome->userId ?? throw new \LogicException('A registration names its account.')),
            RegistrationResult::ONBOARDING_INVALID => throw PortalProblemMap::problem(PortalProblemMap::ONBOARDING_INVALID),
            RegistrationResult::ACCOUNT_DISABLED   => throw PortalProblemMap::problem(PortalProblemMap::ACCOUNT_DISABLED),
        };
    }
}
