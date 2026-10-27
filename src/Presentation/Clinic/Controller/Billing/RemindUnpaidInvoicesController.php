<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\RemindUnpaidInvoices\RemindUnpaidInvoices;
use App\Context\Billing\Application\Command\SendInvoiceToClient\InvoiceDispatchOutcome;
use App\Presentation\Clinic\ViewModel\Billing\DispatchToast;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * « Relancer » one invoice (sheet) or a selection — « Relancer tous les
 * impayés » posts the ids its confirmation listed, never « all »: the
 * reminders are queued and the answer comes back at once with the toast.
 */
#[Route(path: '/billing/reminders', name: 'clinic_invoices_remind', methods: ['POST'])]
final class RemindUnpaidInvoicesController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('billing_invoices_remind', $request->request->getString('_token')))) {
            return new JsonResponse(['error' => 'Token de sécurité invalide. Veuillez réessayer.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $ids = array_values(array_filter($request->request->all('ids'), \is_string(...)));

        if ([] === $ids) {
            return new JsonResponse(['error' => 'Aucune facture sélectionnée.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (\count($ids) > RemindUnpaidInvoices::MAX_INVOICES) {
            return new JsonResponse(
                ['error' => \sprintf('Au plus %d factures par relance : relancez le reste ensuite.', RemindUnpaidInvoices::MAX_INVOICES)],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);
        $user = $this->getUser();

        $outcome = $this->commandBus->dispatch(new RemindUnpaidInvoices(
            clinicId: $currentClinicId->toString(),
            invoiceIds: $ids,
            actorUserId: $user instanceof SecurityUser ? $user->id() : null,
        ));
        \assert($outcome instanceof InvoiceDispatchOutcome);

        return new JsonResponse([
            'message' => DispatchToast::reminded($outcome),
            'queued'  => $outcome->queued,
            'skipped' => $outcome->skipped,
        ]);
    }
}
