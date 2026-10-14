<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Document;

use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\GetCreditNoteDetails;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route(
    path: '/credit-notes/{creditNoteId}/pdf',
    name: 'clinic_credit_note_pdf',
    requirements: ['creditNoteId' => Requirement::UUID],
    methods: ['GET'],
)]
final class CreditNotePdfController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly DocumentPdfFactory $documents,
    ) {
    }

    public function __invoke(string $creditNoteId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        try {
            $note = $this->queryBus->ask(new GetCreditNoteDetails($currentClinicId->toString(), $creditNoteId));
        } catch (\DomainException) {
            throw $this->createNotFoundException('Avoir introuvable.');
        }

        \assert($note instanceof CreditNoteDetails);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        $clinic = $clinic instanceof ClinicDto ? $clinic : null;

        $pdf = $this->documents->render(
            'clinic/document/credit_note.html.twig',
            ['note' => $note],
            $clinic,
            \sprintf('Avoir · %s', $note->number),
            $note->payerName,
        );

        return $this->documents->response($pdf, $note->number);
    }
}
