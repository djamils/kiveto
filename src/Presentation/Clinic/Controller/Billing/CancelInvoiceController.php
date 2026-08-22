<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\CancelInvoice\CancelInvoice;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(path: '/facturation/{invoiceId}/annuler', name: 'clinic_invoice_cancel', methods: ['POST'])]
final class CancelInvoiceController extends AbstractController
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

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('invoice_cancel_' . $invoiceId, $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_invoice_details', ['invoiceId' => $invoiceId]);
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $reason = mb_trim($request->request->getString('reason'));

        try {
            $this->commandBus->dispatch(new CancelInvoice(
                clinicId: $currentClinicId->toString(),
                invoiceId: $invoiceId,
                reason: '' !== $reason ? $reason : 'Annulation au comptoir',
            ));

            $this->addFlash('success', 'Facture annulée.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_invoice_details', ['invoiceId' => $invoiceId]);
    }
}
