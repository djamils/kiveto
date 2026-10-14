<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Prescription;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * The register's bulk bar: the selected ordonnances in one PDF, for the
 * printer or the downloads folder. Drafts and cancelled ones are left out —
 * neither is a document a client may present.
 */
#[Route(path: '/prescriptions/selection.pdf', name: 'clinic_prescriptions_selection_pdf', methods: ['GET'])]
final class PrescriptionsSelectionPdfController extends AbstractController
{
    private const int MAX_DOCUMENTS = 100;

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $clinic   = $this->queryBus->ask(new GetClinic($clinicId));
        $clinic   = $clinic instanceof ClinicDto ? $clinic : null;
        $parts    = [];

        foreach (\array_slice($this->ids($request), 0, self::MAX_DOCUMENTS) as $id) {
            try {
                $prescription = $this->queryBus->ask(new GetPrescriptionDetails($clinicId, $id));
            } catch (\DomainException) {
                continue;
            }

            if (!$prescription instanceof PrescriptionDetails || 'ISSUED' !== $prescription->status) {
                continue;
            }

            $parts[] = $this->documents->render(
                'clinic/document/prescription.html.twig',
                ['prescription' => $prescription],
                $clinic,
                \sprintf('Ordonnance · %s', $prescription->number ?? 'brouillon'),
                \sprintf('%s / %s', $prescription->patientName, $prescription->ownerName),
            );
        }

        if ([] === $parts) {
            throw $this->createNotFoundException('Aucune ordonnance signée dans la sélection.');
        }

        return $this->documents->response(
            1 === \count($parts) ? $parts[0] : $this->documents->merge($parts),
            \sprintf('ordonnances-%d', \count($parts)),
        );
    }

    /**
     * @return list<string>
     */
    private function ids(Request $request): array
    {
        $ids = [];

        foreach ($request->query->all('ids') as $id) {
            if (\is_string($id) && Uuid::isValid($id) && !\in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
