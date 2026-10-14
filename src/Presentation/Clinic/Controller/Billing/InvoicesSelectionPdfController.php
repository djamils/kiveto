<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\GetCreditNoteDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Presentation\Clinic\ViewModel\Document\DocumentPdfFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * The register's bulk bar: the selected invoices (`ids[]`) and credit notes
 * (`av[]`) in one PDF, for the printer. Drafts are left out — they are not
 * invoices yet.
 */
#[Route(path: '/invoices/selection.pdf', name: 'clinic_invoices_selection_pdf', methods: ['GET'])]
final class InvoicesSelectionPdfController extends AbstractController
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

        foreach (\array_slice(self::ids($request, 'ids'), 0, self::MAX_DOCUMENTS) as $id) {
            $invoice = $this->ask(new GetInvoiceDetails($clinicId, $id));

            if ($invoice instanceof InvoiceDetails && null !== $invoice->number) {
                $parts[] = $this->documents->render('clinic/document/invoice.html.twig', ['invoice' => $invoice], $clinic, \sprintf('Facture · %s', $invoice->number), $invoice->payerName);
            }
        }

        foreach (\array_slice(self::ids($request, 'av'), 0, self::MAX_DOCUMENTS) as $id) {
            $note = $this->ask(new GetCreditNoteDetails($clinicId, $id));

            if ($note instanceof CreditNoteDetails) {
                $parts[] = $this->documents->render('clinic/document/credit_note.html.twig', ['note' => $note], $clinic, \sprintf('Avoir · %s', $note->number), $note->payerName);
            }
        }

        if ([] === $parts) {
            throw $this->createNotFoundException('Aucune facture émise dans la sélection.');
        }

        return $this->documents->response(
            1 === \count($parts) ? $parts[0] : $this->documents->merge($parts),
            \sprintf('factures-%d', \count($parts)),
        );
    }

    /**
     * @return list<string>
     */
    public static function ids(Request $request, string $key): array
    {
        $ids = [];

        foreach ($request->query->all($key) as $id) {
            if (\is_string($id) && Uuid::isValid($id) && !\in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function ask(object $query): mixed
    {
        try {
            return $this->queryBus->ask($query);
        } catch (\DomainException) {
            return null;
        }
    }
}
