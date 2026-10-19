<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;
use App\Context\Billing\Application\Command\RecordCounterSale\RecordCounterSale;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Presentation\Clinic\ViewModel\Billing\BillingErrorMessage;
use App\Presentation\Clinic\ViewModel\Billing\BillingSheetBuilder;
use App\Presentation\Clinic\ViewModel\Client\TaxProfileInput;
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
 * `article id:quantity,…`), the means of payment, and who the sale is made
 * to (`payer`): a client of the clinic (`cli`, with `client`), a newcomer
 * whose record is created with it (`part`, with `civ`, `nom`, `pre`, `tel`,
 * `mail`), a professional opened likewise (`pro`, the same fields plus the
 * shared tax-profile block `fisc`, `rs`, `siren`, `tva`), or a client who
 * refuses to give their contact details (`anon`).
 * The invoice is issued and paid at once; the register comes back on its
 * sheet, with the print preview open when the desk hands it over printed
 * (`imprimer`).
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
        $payer    = $request->request->getString('payer');
        $client   = $request->request->getString('client');
        $user     = $this->getUser();

        if (null === $method || [] === $lines) {
            $this->addFlash('error', 'Ajoutez au moins un article et choisissez le moyen de paiement.');

            return $back;
        }

        $taxProfile = 'pro' === $payer ? TaxProfileInput::fromArray($request->request->all()) : null;

        // The « Professionnel » tab only creates structures: a legal name is due even when not VAT-liable.
        if ('pro' === $payer && (null === $taxProfile || null !== $taxProfile->firstError(structureOnly: true))) {
            $this->addFlash('error', $taxProfile?->firstError(structureOnly: true) ?? 'Choisissez le statut fiscal du professionnel.');

            return $back;
        }

        try {
            $invoiceId = $this->commandBus->dispatch(new RecordCounterSale(
                clinicId: $clinicId,
                payerClientId: 'cli' === $payer && Uuid::isValid($client) ? $client : null,
                lines: $lines,
                method: $method->value,
                recordedByUserId: $user instanceof SecurityUser ? $user->id() : null,
                newClient: \in_array($payer, ['part', 'pro'], true) ? $this->newClient($request, $taxProfile) : null,
                contactDetailsRefused: 'anon' === $payer,
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', BillingErrorMessage::of($e));

            return $back;
        }

        \assert(\is_string($invoiceId));

        $invoice = $this->queryBus->ask(new GetInvoiceDetails($clinicId, $invoiceId));

        if ($invoice instanceof InvoiceDetails) {
            $this->addFlash('success', \sprintf(
                '%s émise et encaissée · %s%s',
                $invoice->number ?? 'Facture',
                BillingSheetBuilder::euros($invoice->totalGrossMinorUnits),
                match ($payer) {
                    'part'  => \sprintf(' · fiche créée pour %s %s', 'M.' === $request->request->getString('civ') ? 'M.' : 'Mme', $invoice->payerName),
                    'pro'   => \sprintf(' · fiche créée pour %s', $invoice->payerName),
                    default => '',
                },
            ));
        }

        return $this->redirectToRoute('clinic_invoice_details', [
            'invoiceId' => $invoiceId,
            'per'       => '0',
            ...($request->request->getBoolean('imprimer') ? ['imprimer' => '1'] : []),
        ]);
    }

    /**
     * The « Nouveau particulier » or « Nouveau professionnel » form, as the
     * window normalised it; the Billing context checks it.
     */
    private function newClient(Request $request, ?TaxProfileInput $taxProfile): CounterSaleNewClient
    {
        $field = static fn (string $name): string => mb_trim($request->request->getString($name));

        return new CounterSaleNewClient(
            civility: 'M.' === $field('civ') ? CounterSaleNewClient::MISTER : CounterSaleNewClient::MADAM,
            // Typed in capitals, kept as « Benali » on the record.
            lastName: mb_strtoupper(mb_substr($field('nom'), 0, 1)) . mb_strtolower(mb_substr($field('nom'), 1)),
            firstName: $field('pre'),
            phone: (string) preg_replace('/\D+/', '', $field('tel')),
            email: mb_strtolower($field('mail')),
            taxProfile: $taxProfile?->toBillingInput(),
        );
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
