<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

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
 * Cashes an invoice from the client sheet and comes back to it, where the
 * invoice page would otherwise take the receptionist away from the file.
 */
#[Route('/clients/{id}/payments', name: 'clinic_clients_payment_record', methods: ['POST'])]
final class RecordClientPaymentController extends AbstractClientSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $clinicId  = $this->clinicId();
        $invoiceId = $request->request->getString('invoice_id');
        $invoice   = '' === $invoiceId ? null : $this->queryBus->ask(new GetInvoiceDetails($clinicId, $invoiceId));

        // The invoice must be one of this client's: the id comes from the form.
        if (!$invoice instanceof InvoiceDetails || $invoice->payerClientId !== $id) {
            $this->addFlash('error', 'Cette facture n’appartient pas à ce client.');

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
                clinicId: $clinicId,
                invoiceId: $invoiceId,
                method: $request->request->getString('method'),
                amountMinorUnits: $amount,
                reference: $this->trimmed($request, 'reference'),
                recordedByUserId: $user instanceof SecurityUser ? $user->id() : null,
            ));

            $this->addFlash('success', 'Paiement enregistré.');
        } catch (\ValueError) {
            $this->addFlash('error', 'Moyen de paiement inconnu.');

            return $this->backToSheet($id);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->backToSheet($id);
        }

        return $this->backToSheet($id, 'cCompte');
    }
}
