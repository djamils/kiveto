<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\SubmitPetChangeRequest\SubmitPetChangeRequest;
use App\Context\ClientPortal\Application\Port\PortalRequestQuotaInterface;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\PetNotEligibleException;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Context\ClientPortal\Domain\Exception\PortalRequestRateLimitedException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\PetChangeRequestCreation;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\PortalRequestCreatedView;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Keyed (Idempotency-Key). The 24 h quota is consumed before the command,
 * so a request refused afterwards still counts.
 *
 * @implements ProcessorInterface<PetChangeRequestCreation, PortalRequestCreatedView>
 */
final readonly class PetChangeRequestProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private PortalRequestQuotaInterface $quota,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PortalRequestCreatedView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId = $uriVariables['clinicId'] ?? null;
        $petId    = $uriVariables['petId'] ?? null;
        \assert(\is_string($clinicId) && \is_string($petId));
        \assert(null !== $data->field && null !== $data->proposedValue);

        $this->guard->require($user->id(), $clinicId);

        try {
            $this->quota->consume($user->id(), $clinicId);
            $id = $this->commandBus->dispatch(new SubmitPetChangeRequest(
                $user->id(),
                $clinicId,
                $petId,
                $data->field,
                $data->proposedValue,
                null === $data->measuredOn ? null : new \DateTimeImmutable($data->measuredOn),
                $data->note,
            ));
        } catch (PetNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        } catch (PetNotEligibleException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::PET_NOT_ELIGIBLE, previous: $exception);
        } catch (InvalidPortalRequestException $invalid) {
            throw PortalProblemMap::violation($invalid->propertyPath, $invalid->getMessage());
        } catch (PortalRequestRateLimitedException $limited) {
            throw PortalProblemMap::problem(PortalProblemMap::RATE_LIMITED, null === $limited->retryAfterSeconds ? [] : ['Retry-After' => (string) $limited->retryAfterSeconds]);
        }
        \assert(\is_string($id));

        return new PortalRequestCreatedView($id);
    }
}
