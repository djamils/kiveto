<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\RecordInvoicePayment\RecordInvoicePayment;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Presentation\Clinic\ViewModel\Billing\BillingErrorMessage;
use App\Presentation\Clinic\ViewModel\Billing\BillingSheetBuilder;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * « Encaisser » on an issued invoice: one payment, of any part of what is
 * left — a bill split over several means is several payments.
 */
#[Route(path: '/invoices/{invoiceId}/payment', name: 'clinic_invoice_pay', methods: ['POST'])]
final class RecordPaymentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, string $invoiceId): Response
    {
        $back = $this->redirectToRoute('clinic_invoice_details', ['invoiceId' => $invoiceId]);

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('invoice_pay_' . $invoiceId, $request->request->getString('_token')))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $back;
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $amount   = AmountParser::toMinorUnits($request->request->getString('amount'));

        if (null === $amount || $amount <= 0) {
            $this->addFlash('error', 'Le montant du règlement est invalide.');

            return $back;
        }

        $user      = $this->getUser();
        $reference = mb_trim($request->request->getString('reference'));
        $method    = PaymentMethod::tryFrom($request->request->getString('method'));

        if (null === $method) {
            $this->addFlash('error', 'Moyen de paiement inconnu.');

            return $back;
        }

        try {
            $this->commandBus->dispatch(new RecordInvoicePayment(
                clinicId: $clinicId,
                invoiceId: $invoiceId,
                method: $method->value,
                amountMinorUnits: $amount,
                reference: '' !== $reference ? $reference : null,
                recordedByUserId: $user instanceof SecurityUser ? $user->id() : null,
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', BillingErrorMessage::of($e));

            return $back;
        }

        $invoice = $this->queryBus->ask(new GetInvoiceDetails($clinicId, $invoiceId));
        $left    = $invoice instanceof InvoiceDetails ? max(0, $invoice->balanceDueMinorUnits) : 0;

        $this->addFlash('success', \sprintf(
            '%s encaissés · %s%s',
            BillingSheetBuilder::euros($amount),
            mb_strtolower($method->label()),
            $left > 0 ? ' · reste ' . BillingSheetBuilder::euros($left) : ' · facture soldée',
        ));

        return $back;
    }
}
