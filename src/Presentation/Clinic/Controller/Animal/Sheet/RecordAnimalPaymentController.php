<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Billing\Application\Command\RecordInvoicePayment\RecordInvoicePayment;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Cashes the invoice of a visit from the animal's history and comes back to
 * the sheet. A closed file (deceased, archived) can still settle its debts.
 */
#[Route('/animals/{id}/payments', name: 'clinic_animals_payment_record', methods: ['POST'])]
final class RecordAnimalPaymentController extends AbstractAnimalSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $animal    = $this->animal($id);
        $invoiceId = $request->request->getString('invoice_id');
        $invoice   = '' === $invoiceId ? null : $this->queryBus->ask(new GetInvoiceDetails($animal->clinicId, $invoiceId));
        $payers    = array_map(static fn ($ownership): string => $ownership->clientId, $animal->ownerships);

        // The invoice must be paid by one of the animal's owners, past or present: the id comes from the form.
        if (!$invoice instanceof InvoiceDetails || !\in_array($invoice->payerClientId, $payers, true)) {
            $this->addFlash('error', 'Cette facture ne concerne pas cet animal.');

            return $this->backToSheet($id);
        }

        $amount = AmountParser::toMinorUnits($request->request->getString('amount'));

        if (null === $amount || $amount <= 0) {
            $this->addFlash('error', 'Le montant du paiement est invalide.');

            return $this->backToSheet($id);
        }

        $user = $this->getUser();

        try {
            $this->commandBus->dispatch(new RecordInvoicePayment(
                clinicId: $animal->clinicId,
                invoiceId: $invoiceId,
                method: $request->request->getString('method'),
                amountMinorUnits: $amount,
                reference: $this->trimmed($request, 'reference'),
                recordedByUserId: $user instanceof SecurityUser ? $user->id() : null,
            ));
        } catch (\ValueError) {
            $this->addFlash('error', 'Moyen de paiement inconnu.');

            return $this->backToSheet($id);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->backToSheet($id);
        }

        $this->addFlash('success', 'Paiement enregistré.');

        return $this->backToSheet($id, 'p-chrono');
    }
}
