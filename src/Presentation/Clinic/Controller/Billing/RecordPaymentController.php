<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\RecordInvoicePayment\RecordInvoicePayment;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(path: '/invoices/{invoiceId}/payment', name: 'clinic_invoice_pay', methods: ['POST'])]
final class RecordPaymentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, string $invoiceId): Response
    {
        $token = $request->request->getString('_token');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('invoice_pay_' . $invoiceId, $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_invoice_details', ['invoiceId' => $invoiceId]);
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $amount = AmountParser::toMinorUnits($request->request->getString('amount'));

        if (null === $amount || $amount <= 0) {
            $this->addFlash('error', 'Le montant du paiement est invalide.');

            return $this->redirectToRoute('clinic_invoice_details', ['invoiceId' => $invoiceId]);
        }

        $user      = $this->getUser();
        $reference = mb_trim($request->request->getString('reference'));

        try {
            $this->commandBus->dispatch(new RecordInvoicePayment(
                clinicId: $currentClinicId->toString(),
                invoiceId: $invoiceId,
                method: $request->request->getString('method'),
                amountMinorUnits: $amount,
                reference: '' !== $reference ? $reference : null,
                recordedByUserId: $user instanceof SecurityUser ? $user->id() : null,
            ));

            $this->addFlash('success', 'Paiement enregistré.');
        } catch (\ValueError) {
            $this->addFlash('error', 'Moyen de paiement inconnu.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_invoice_details', ['invoiceId' => $invoiceId]);
    }
}
