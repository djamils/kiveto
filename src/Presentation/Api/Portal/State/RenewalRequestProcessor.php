<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\SubmitRenewalRequest\SubmitRenewalRequest;
use App\Context\ClientPortal\Application\Port\PortalRequestQuotaInterface;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\PortalRequestRateLimitedException;
use App\Context\ClientPortal\Domain\Exception\PrescriptionNotFoundException;
use App\Context\ClientPortal\Domain\Exception\PrescriptionNotRenewableException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\RenewalRequestCreation;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\RenewalRequestCreatedView;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Keyed (Idempotency-Key). The 24 h quota is consumed before the command;
 * a submit while a request is open answers that request.
 *
 * @implements ProcessorInterface<RenewalRequestCreation, RenewalRequestCreatedView>
 */
final readonly class RenewalRequestProcessor implements ProcessorInterface
{
    /** The `detail` of `prescription_not_renewable` per reason; none for the generic one. */
    private const array DETAILS = [
        PrescriptionNotRenewableException::PET_NOT_ALIVE     => 'L\'animal de cette ordonnance n\'est plus suivi.',
        PrescriptionNotRenewableException::RECENTLY_REJECTED => 'Une demande de renouvellement de cette ordonnance a été refusée il y a moins de 7 jours.',
    ];

    public function __construct(
        private CommandBusInterface $commandBus,
        private PortalRequestQuotaInterface $quota,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RenewalRequestCreatedView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId       = $uriVariables['clinicId'] ?? null;
        $prescriptionId = $uriVariables['prescriptionId'] ?? null;
        \assert(\is_string($clinicId) && \is_string($prescriptionId));
        \assert(null !== $data->petCondition);

        $this->guard->require($user->id(), $clinicId);

        try {
            $this->quota->consume($user->id(), $clinicId);
            $id = $this->commandBus->dispatch(new SubmitRenewalRequest($user->id(), $clinicId, $prescriptionId, $data->petCondition, $data->note));
        } catch (PrescriptionNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        } catch (PrescriptionNotRenewableException $exception) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_RENEWABLE, detail: self::DETAILS[$exception->reason] ?? null, previous: $exception);
        } catch (InvalidPortalRequestException $invalid) {
            throw PortalProblemMap::violation($invalid->propertyPath, $invalid->getMessage());
        } catch (PortalRequestRateLimitedException $limited) {
            throw PortalProblemMap::problem(PortalProblemMap::RATE_LIMITED, null === $limited->retryAfterSeconds ? [] : ['Retry-After' => (string) $limited->retryAfterSeconds]);
        }
        \assert(\is_string($id));

        return new RenewalRequestCreatedView($id);
    }
}
