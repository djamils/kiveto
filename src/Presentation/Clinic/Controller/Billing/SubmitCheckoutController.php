<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\ApplyInvoiceDiscount\ApplyInvoiceDiscount;
use App\Context\Billing\Application\Command\CheckoutAdmission\CheckoutAdmission;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Domain\Exception\InvoiceNotTransmissibleException;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Presentation\Clinic\Form\Shared\AmountParser;
use App\Presentation\Clinic\ViewModel\Billing\BillingErrorMessage;
use App\Presentation\Clinic\ViewModel\Billing\BillingSheetBuilder;
use App\Presentation\Clinic\ViewModel\Billing\ReadinessMessages;
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

#[Route(path: '/checkout/{admissionId}', name: 'clinic_checkout_submit', methods: ['POST'])]
final class SubmitCheckoutController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ReadinessMessages $readiness,
    ) {
    }

    public function __invoke(Request $request, string $admissionId): Response
    {
        $token = $request->request->getString('_token');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('checkout_' . $admissionId, $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_checkout', ['admissionId' => $admissionId]);
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId  = $currentClinicId->toString();
        $invoiceId = $request->request->getString('invoice_id');
        $amount    = AmountParser::toMinorUnits($request->request->getString('amount'));

        if ('' === $invoiceId || null === $amount || $amount < 0) {
            $this->addFlash('error', 'Le montant encaissé est invalide.');

            return $this->redirectToRoute('clinic_checkout', ['admissionId' => $admissionId]);
        }

        $user      = $this->getUser();
        $reference = mb_trim($request->request->getString('reference'));

        if (null === PaymentMethod::tryFrom($request->request->getString('method'))) {
            $this->addFlash('error', 'Moyen de paiement inconnu.');

            return $this->redirectToRoute('clinic_checkout', ['admissionId' => $admissionId]);
        }

        try {
            $this->applyDiscount($request, $clinicId, $invoiceId);

            $this->commandBus->dispatch(new CheckoutAdmission(
                clinicId: $clinicId,
                invoiceId: $invoiceId,
                method: $request->request->getString('method'),
                amountMinorUnits: $amount,
                reference: '' !== $reference ? $reference : null,
                recordedByUserId: $user instanceof SecurityUser ? $user->id() : null,
            ));
        } catch (InvoiceNotTransmissibleException $e) {
            // Nothing was numbered: the sheet of the draft lists what to fix, each with its link.
            $draft = $this->queryBus->ask(new GetInvoiceDetails($clinicId, $invoiceId));

            ReadinessMessages::flash($request, $invoiceId, $this->readiness->for(
                $e->requirements,
                $draft instanceof InvoiceDetails ? $draft->payerClientId : null,
                'clinic_checkout|admissionId=' . rawurlencode($admissionId),
            ));

            return $this->redirectToRoute('clinic_checkout', ['admissionId' => $admissionId]);
        } catch (\DomainException $e) {
            $this->addFlash('error', BillingErrorMessage::of($e));

            return $this->redirectToRoute('clinic_checkout', ['admissionId' => $admissionId]);
        }

        $invoice = $this->queryBus->ask(new GetInvoiceDetails($clinicId, $invoiceId));

        if ($invoice instanceof InvoiceDetails) {
            $left = max(0, $invoice->balanceDueMinorUnits);

            $this->addFlash('success', \sprintf(
                '%s émise · %s encaissés%s',
                $invoice->number ?? 'Facture',
                BillingSheetBuilder::euros($amount),
                $left > 0 ? ' · reste ' . BillingSheetBuilder::euros($left) : ' · visite clôturée',
            ));
        }

        return $this->redirectToRoute('clinic_invoice_details', ['invoiceId' => $invoiceId]);
    }

    /**
     * The discount is part of the same form: it has to land on the invoice
     * before it is issued, since an issued invoice no longer moves.
     */
    private function applyDiscount(Request $request, string $clinicId, string $invoiceId): void
    {
        $percent = mb_trim($request->request->getString('discount_percent'));

        if ('' === $percent || '0' === $percent) {
            return;
        }

        $reason = mb_trim($request->request->getString('discount_reason'));

        $this->commandBus->dispatch(new ApplyInvoiceDiscount(
            clinicId: $clinicId,
            invoiceId: $invoiceId,
            kind: 'PERCENTAGE',
            value: str_replace(',', '.', $percent),
            reason: '' !== $reason ? $reason : null,
        ));
    }
}
