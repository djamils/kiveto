<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\IssueCreditNote\IssueCreditNote;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\GetCreditNoteDetails;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
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
use Symfony\Component\Uid\Uuid;

/**
 * « Établir l'avoir »: the lines chosen, the reason, and — when the client had
 * already paid more than is left — how it is given back (card or cash) or
 * kept as a credit. Comes back to the new credit note's sheet.
 */
#[Route(path: '/invoices/{invoiceId}/credit-note', name: 'clinic_invoice_credit_note', methods: ['POST'])]
final class IssueCreditNoteController extends AbstractController
{
    /** What the window offers to give back an overpayment: card, cash, or keep it as a credit. */
    private const array REFUNDS = ['cb' => PaymentMethod::CARD, 'esp' => PaymentMethod::CASH];

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

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('invoice_credit_note_' . $invoiceId, $request->request->getString('_token')))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $back;
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $reason   = mb_substr(mb_trim($request->request->getString('reason')), 0, 255);
        $lines    = array_values(array_filter(
            explode(',', $request->request->getString('lines')),
            static fn (string $id): bool => Uuid::isValid($id),
        ));

        if ('' === $reason || [] === $lines) {
            $this->addFlash('error', 'Choisissez les lignes à créditer et le motif de l\'avoir.');

            return $back;
        }

        $user = $this->getUser();

        try {
            $creditNoteId = $this->commandBus->dispatch(new IssueCreditNote(
                clinicId: $clinicId,
                invoiceId: $invoiceId,
                lineIds: $lines,
                reason: $reason,
                refundMethod: (self::REFUNDS[$request->request->getString('refund')] ?? null)?->value,
                issuedByUserId: $user instanceof SecurityUser ? $user->id() : null,
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', BillingErrorMessage::of($e));

            return $back;
        }

        \assert(\is_string($creditNoteId));

        $note = $this->queryBus->ask(new GetCreditNoteDetails($clinicId, $creditNoteId));

        if ($note instanceof CreditNoteDetails) {
            $this->addFlash('success', \sprintf(
                'Avoir %s établi · −%s%s',
                $note->number,
                BillingSheetBuilder::euros($note->totalGrossMinorUnits),
                $note->refundedMinorUnits > 0 ? \sprintf(' · %s à rendre (%s)', BillingSheetBuilder::euros($note->refundedMinorUnits), mb_strtolower($note->refundMethodLabel ?? '')) : '',
            ));
        }

        return $this->redirectToRoute('clinic_credit_note_details', ['creditNoteId' => $creditNoteId]);
    }
}
