<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\PrepareInvoiceForAdmission\PrepareInvoiceForAdmission;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The checkout screen of a visit.
 *
 * Opening it prepares the invoice — the draft is (re)built from what the
 * practitioner recorded — so the desk always sees the current state of the
 * visit rather than a snapshot taken at some earlier moment.
 */
#[Route(path: '/checkout/{admissionId}', name: 'clinic_checkout', methods: ['GET'])]
final class ShowCheckoutController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $admissionId): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        try {
            $invoiceId = $this->commandBus->dispatch(new PrepareInvoiceForAdmission(
                clinicId: $clinicId,
                admissionId: $admissionId,
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('clinic_admission_queue');
        }

        \assert(\is_string($invoiceId));

        $invoice = $this->queryBus->ask(new GetInvoiceDetails($clinicId, $invoiceId));
        \assert($invoice instanceof InvoiceDetails);

        return $this->render('clinic/billing/checkout/index.html.twig', [
            'invoice'     => $invoice,
            'admissionId' => $admissionId,
            'methods'     => PaymentMethod::cases(),
        ]);
    }
}
