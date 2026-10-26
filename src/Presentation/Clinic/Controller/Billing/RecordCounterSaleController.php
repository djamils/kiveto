<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Billing;

use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;
use App\Context\Billing\Application\Command\RecordCounterSale\RecordCounterSale;
use App\Context\Billing\Application\Query\GetInvoiceDetails\GetInvoiceDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Domain\Exception\InvoiceNotTransmissibleException;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Presentation\Clinic\ViewModel\Billing\BillingErrorMessage;
use App\Presentation\Clinic\ViewModel\Billing\BillingSheetBuilder;
use App\Presentation\Clinic\ViewModel\Billing\ReadinessMessages;
use App\Presentation\Clinic\ViewModel\Client\TaxProfileInput;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
 * shared tax-profile block `fisc`, `rs`, `siren`, `tva` and its postal address
 * `adr`, `cp`, `ville`, required when it is VAT-liable), or a client who
 * refuses to give their contact details (`anon`).
 * The invoice is issued and paid at once; the register comes back on its
 * sheet, with the print preview open when the desk hands it over printed
 * (`imprimer`).
 *
 * The window posts in the background (`Accept: application/json`): a refusal
 * comes back as `{error}` or, for a professional invoice that cannot be
 * numbered, `{readiness: [{text, href, label}]}` shown inline while the window
 * keeps what was typed; a success as `{redirect}`. A plain form post gets the
 * same outcomes as a toast and a redirect.
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
        private readonly ReadinessMessages $readiness,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $json = 'json' === $request->getPreferredFormat();

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('billing_counter_sale', $request->request->getString('_token')))) {
            return $this->refuse($json, 'Token de sécurité invalide. Veuillez réessayer.');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();
        $method   = self::METHODS[$request->request->getString('mode')] ?? null;
        $lines    = $this->lines($request->request->getString('lines'));
        $payer    = $request->request->getString('payer');
        $client   = $request->request->getString('client');
        $clientId = 'cli' === $payer && Uuid::isValid($client) ? $client : null;
        $user     = $this->getUser();

        if (null === $method || [] === $lines) {
            return $this->refuse($json, 'Ajoutez au moins un article et choisissez le moyen de paiement.');
        }

        $taxProfile = 'pro' === $payer ? TaxProfileInput::fromArray($request->request->all()) : null;

        // The « Professionnel » tab only creates structures: a legal name is due even when not VAT-liable.
        if ('pro' === $payer && (null === $taxProfile || null !== $taxProfile->firstError(structureOnly: true))) {
            return $this->refuse($json, $taxProfile?->firstError(structureOnly: true) ?? 'Choisissez le statut fiscal du professionnel.');
        }

        $newClient = \in_array($payer, ['part', 'pro'], true) ? $this->newClient($request, $taxProfile) : null;

        // A VAT-liable professional is invoiced at its postal address (France).
        if (true === $taxProfile?->isProfessional() && null !== $newClient && !self::hasFrenchAddress($newClient)) {
            return $this->refuse($json, 'Indiquez l\'adresse du professionnel : adresse, code postal et ville.');
        }

        try {
            $invoiceId = $this->commandBus->dispatch(new RecordCounterSale(
                clinicId: $clinicId,
                payerClientId: $clientId,
                lines: $lines,
                method: $method->value,
                recordedByUserId: $user instanceof SecurityUser ? $user->id() : null,
                newClient: $newClient,
                contactDetailsRefused: 'anon' === $payer,
            ));
        } catch (InvoiceNotTransmissibleException $e) {
            // Nothing was created: the window lists what to fix; a newcomer has no sheet to link to yet.
            $messages = $this->readiness->for($e->requirements, $clientId, 'clinic_invoices');

            if ($json) {
                return new JsonResponse(['readiness' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return $this->refuse(false, implode(' · ', array_column($messages, 'text')));
        } catch (\DomainException $e) {
            return $this->refuse($json, BillingErrorMessage::of($e));
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

        $target = $this->generateUrl('clinic_invoice_details', [
            'invoiceId' => $invoiceId,
            'per'       => '0',
            ...($request->request->getBoolean('imprimer') ? ['imprimer' => '1'] : []),
        ]);

        return $json ? new JsonResponse(['redirect' => $target]) : $this->redirect($target);
    }

    private function refuse(bool $json, string $message): Response
    {
        if ($json) {
            return new JsonResponse(['error' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->addFlash('error', $message);

        return $this->redirectToRoute('clinic_invoices');
    }

    private static function hasFrenchAddress(CounterSaleNewClient $client): bool
    {
        return $client->hasAddress() && 1 === preg_match('/^\d{5}$/', $client->postalCode);
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
            // Only a professional's tab asks for the address.
            street: null !== $taxProfile ? $field('adr') : '',
            postalCode: null !== $taxProfile ? (string) preg_replace('/\D+/', '', $field('cp')) : '',
            city: null !== $taxProfile ? $field('ville') : '',
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
