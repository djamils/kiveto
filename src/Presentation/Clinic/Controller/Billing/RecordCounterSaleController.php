<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\RecordCounterSale\RecordCounterSale;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
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
 * « Vente au comptoir »: the articles and their quantities (`lines` as
 * `article id:quantity,…`), the client if one was chosen, the means of
 * payment. The invoice is issued and paid at once; the register comes back
 * on its sheet.
 */
#[Route(path: '/billing/counter-sale', name: 'clinic_counter_sale', methods: ['POST'])]
final class RecordCounterSaleController extends AbstractController
{
    private const array METHODS = ['cb' => PaymentMethod::CARD, 'esp' => PaymentMethod::CASH, 'chq' => PaymentMethod::CHECK];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $back = $this->redirectToRoute('clinic_invoices');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('billing_counter_sale', $request->request->getString('_token')))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $back;
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $method   = self::METHODS[$request->request->getString('mode')] ?? null;
        $lines    = $this->lines($request->request->getString('lines'));
        $client   = $request->request->getString('client');
        $user     = $this->getUser();

        if (null === $method || [] === $lines) {
            $this->addFlash('error', 'Ajoutez au moins un article et choisissez le moyen de paiement.');

            return $back;
        }

        try {
            $invoiceId = $this->commandBus->dispatch(new RecordCounterSale(
                clinicId: $clinicId,
                payerClientId: Uuid::isValid($client) ? $client : null,
                lines: $lines,
                method: $method->value,
                recordedByUserId: $user instanceof SecurityUser ? $user->id() : null,
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', BillingErrorMessage::of($e));

            return $back;
        }

        \assert(\is_string($invoiceId));

        $invoice = $this->queryBus->ask(new GetInvoiceDetails($clinicId, $invoiceId));

        if ($invoice instanceof InvoiceDetails) {
            $this->addFlash('success', \sprintf(
                '%s émise et encaissée · %s',
                $invoice->number ?? 'Facture',
                BillingSheetBuilder::euros($invoice->totalGrossMinorUnits),
            ));
        }

        return $this->redirectToRoute('clinic_invoice_details', ['invoiceId' => $invoiceId, 'per' => '0']);
    }

    /**
     * @return list<array{articleId: string, quantity: int}>
     */
    private function lines(string $raw): array
    {
        $lines = [];

        foreach (explode(',', $raw) as $pair) {
            [$article, $quantity] = array_pad(explode(':', $pair, 2), 2, '');

            if (Uuid::isValid($article) && ctype_digit($quantity) && (int) $quantity > 0) {
                $lines[] = ['articleId' => $article, 'quantity' => (int) $quantity];
            }
        }

        return $lines;
    }
}
