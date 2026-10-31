<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Port\PrescriptionContentLine;
use App\Context\ClientPortal\Application\Query\GetPortalPrescription\GetPortalPrescription;
use App\Context\ClientPortal\Application\Query\GetPortalPrescription\PortalPrescription as Prescription;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\PortalPrescription;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\ApiDate;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\PetReferenceView;
use App\Presentation\Api\Portal\View\PrescriptionLineView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<PortalPrescription>
 */
final readonly class PortalPrescriptionProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PortalPrescription
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId       = $uriVariables['clinicId'] ?? null;
        $prescriptionId = $uriVariables['prescriptionId'] ?? null;
        \assert(\is_string($clinicId) && \is_string($prescriptionId));

        $this->guard->require($user->id(), $clinicId);

        $prescription = $this->queryBus->ask(new GetPortalPrescription($user->id(), $clinicId, $prescriptionId));

        if (!$prescription instanceof Prescription) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }

        return new PortalPrescription(
            $prescription->id,
            ApiDate::formatLocal($prescription->issuedAt),
            $prescription->vet,
            new PetReferenceView($prescription->petId, $prescription->petName),
            array_map(
                static fn (PrescriptionContentLine $line): PrescriptionLineView => new PrescriptionLineView($line->label, $line->quantity, $line->posology),
                $prescription->lines,
            ),
        );
    }
}
