<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\Client\Domain\Exception\ClientArchivedCannotBeModifiedException;
use App\Context\ClientPortal\Application\Command\SetPortalConsent\SetPortalConsent;
use App\Context\ClientPortal\Application\Query\GetPortalConsents\GetPortalConsents;
use App\Context\ClientPortal\Application\Query\GetPortalConsents\PortalConsents;
use App\Context\ClientPortal\Domain\Exception\ClinicNotLinkedException;
use App\Context\ClientPortal\Domain\Exception\ConsentConflictException;
use App\Context\ClientPortal\Domain\Exception\InvalidConsentChoiceException;
use App\Context\ClientPortal\Domain\Exception\OwnerProfileNotFoundException;
use App\Context\ClientPortal\Domain\Exception\UnknownConsentKindException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\Consents;
use App\Presentation\Api\Portal\Resource\ConsentUpdate;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Not keyed: setting the same channels again is a no-op answered 200. A
 * file archived between the read and the write of the command rolls the
 * whole change back: `409 conflict`, the retry skips that file.
 *
 * @implements ProcessorInterface<ConsentUpdate, Consents>
 */
final readonly class UpdateConsentProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Consents
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        $kind     = $uriVariables['kind'] ?? null;
        \assert(\is_string($clinicId) && \is_string($kind));

        $this->guard->require($user->id(), $clinicId);

        try {
            $this->commandBus->dispatch(new SetPortalConsent($user->id(), $clinicId, $kind, $data->channels));
        } catch (ClinicNotLinkedException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        } catch (UnknownConsentKindException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        } catch (OwnerProfileNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        } catch (InvalidConsentChoiceException $exception) {
            throw PortalProblemMap::violation('channels', $exception->getMessage(), $data->channels);
        } catch (ConsentConflictException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::CONFLICT, previous: $exception);
        } catch (ClientArchivedCannotBeModifiedException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::CONFLICT, previous: $exception);
        }

        $consents = $this->queryBus->ask(new GetPortalConsents($user->id(), $clinicId));
        \assert($consents instanceof PortalConsents);

        return Consents::of($consents);
    }
}
