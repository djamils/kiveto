<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Billing;

use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId as ClientClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Tests\Support\Billing\SellerIdentities;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * « Envoyer » and « Relancer » from the Facturation page: the endpoints
 * queue and answer the toast at once; the sheet offers both acts.
 */
final class InvoiceDispatchEndpointsTest extends WebTestCase
{
    use Factories;

    private const string CLINIC  = '01960000-0000-7000-8000-00000000e701';
    private const string INVOICE = '01960000-0000-7000-8000-00000000e702';
    private const string CLIENT  = '01960000-0000-7000-8000-00000000e703';
    private const string HOST    = 'http://clinic.kiveto.local';

    private KernelBrowser $browser;

    /** @var array{send: string, remind: string} */
    private array $tokens = ['send' => '', 'remind' => ''];

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique Montmartre')->with(['timeZone' => 'Europe/Paris'])->create();
        $admin = ClinicUserFactory::new()->withEmail('claire.admin@example.com')->withPlainPassword('mot-de-passe')->create();
        ClinicMembershipEntityFactory::new()->withClinicId(self::CLINIC)->withUserId($admin->getId()->toRfc4122())->asManager()->asEmployee()->asClinicAdministrator()->create();
        $this->seedInvoice();

        $this->browser->request('POST', self::HOST . '/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"email":"claire.admin@example.com","password":"mot-de-passe"}');
        self::assertResponseIsSuccessful();

        $this->browser->request('GET', self::HOST . '/invoices');
        self::assertResponseIsSuccessful();
        $payload = (string) $this->browser->getCrawler()->filter('.fact[data-payload]')->attr('data-payload');
        self::assertSame(1, preg_match('/"send":"([^"]+)","remind":"([^"]+)"/', $payload, $tokens));
        $this->tokens = ['send' => $tokens[1], 'remind' => $tokens[2]];
        self::assertStringContainsString('"mail":true', $payload, 'The register knows who can receive an invoice.');
    }

    public function testAnInvoiceIsSentThenReminded(): void
    {
        $sheet = $this->json('GET', '/invoices/' . self::INVOICE . '/sheet');
        self::assertNull($this->tip($sheet, 'send'));
        self::assertNull($this->tip($sheet, 'remind'));

        $sent = $this->json('POST', '/billing/invoice-sends', ['_token' => $this->tokens['send'], 'ids' => [self::INVOICE, self::CLIENT]]);
        self::assertSame("Facture en cours d'envoi par e-mail · 1 non envoyée : introuvable", $sent['message']);

        $preview = $this->json('GET', '/billing/reminders/preview?all=1');
        self::assertSame(['invoices' => 1, 'ids' => [self::INVOICE], 'more' => 0, 'clients' => 1, 'sms' => 0, 'segments' => 0, 'skipped' => 0], $preview);

        // « Relancer tous les impayés » posts the ids its confirmation counted.
        $reminded = $this->json('POST', '/billing/reminders', ['_token' => $this->tokens['remind'], 'ids' => $preview['ids']]);
        self::assertSame("Relance en cours d'envoi", $reminded['message']);

        $sheet = $this->json('GET', '/invoices/' . self::INVOICE . '/sheet');
        self::assertSame(1, $sheet['relances']);
        self::assertSame("Déjà relancée aujourd'hui", $this->tip($sheet, 'remind'));
        self::assertIsArray($sheet['hist']);
        self::assertContains('Envoi par e-mail le ' . new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'))->format('d/m/Y') . ' · En cours', array_column($sheet['hist'], 't'));

        $again = $this->json('POST', '/billing/reminders', ['_token' => $this->tokens['remind'], 'ids' => [self::INVOICE]]);
        self::assertSame("Aucune facture relancée · 1 non relancée : déjà relancée aujourd'hui", $again['message']);
        self::assertSame(2, $this->connection()->fetchOne("SELECT COUNT(*) FROM shared__messenger_messages WHERE body LIKE '%SendInvoiceEmail%' OR body LIKE '%SendUnpaidReminder%'"));
    }

    public function testRequestsWithoutTokenOrInvoiceAreRefused(): void
    {
        $this->browser->request('POST', self::HOST . '/billing/invoice-sends', ['_token' => 'nope', 'ids' => [self::INVOICE]], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST', self::HOST . '/billing/reminders', ['_token' => 'nope', 'ids' => [self::INVOICE]], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(403);

        $this->browser->request('POST', self::HOST . '/billing/invoice-sends', ['_token' => $this->tokens['send']], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        $this->browser->request('POST', self::HOST . '/billing/reminders', ['_token' => $this->tokens['remind']], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        // « all » is no longer a reminder: only the ids the confirmation showed are.
        $this->browser->request('POST', self::HOST . '/billing/reminders', ['_token' => $this->tokens['remind'], 'all' => '1'], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);

        $this->browser->request('POST', self::HOST . '/billing/reminders', ['_token' => $this->tokens['remind'], 'ids' => array_fill(0, 501, self::INVOICE)], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Au plus 500 factures par relance', (string) $this->browser->getResponse()->getContent());
    }

    /**
     * @param array<string, mixed> $sheet
     */
    private function tip(array $sheet, string $act): mixed
    {
        self::assertIsArray($sheet['actions']);
        self::assertIsArray($sheet['actions'][$act] ?? null, $act);
        self::assertArrayHasKey('tip', $sheet['actions'][$act]);

        return $sheet['actions'][$act]['tip'];
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, array $parameters = []): array
    {
        $this->browser->request($method, self::HOST . $path, $parameters, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->browser->getResponse()->getContent(), true, 32, \JSON_THROW_ON_ERROR);
        \assert(\is_array($data));

        $named = [];

        foreach ($data as $key => $value) {
            $named[(string) $key] = $value;
        }

        return $named;
    }

    private function seedInvoice(): void
    {
        $clients = self::getContainer()->get(ClientRepositoryInterface::class);
        \assert($clients instanceof ClientRepositoryInterface);
        $clients->save(Client::create(
            ClientId::fromString(self::CLIENT),
            ClientClinicId::fromString(self::CLINIC),
            new ClientIdentity('Sophie', 'Dubois', Civility::MADAM),
            [ContactMethod::email(EmailAddress::fromString('s.dubois@email.fr'), ContactLabel::HOME, true)],
            new \DateTimeImmutable('2026-01-12 10:00:00'),
        ));

        $now     = new \DateTimeImmutable('2026-10-16 08:30:00', new \DateTimeZone('UTC'));
        $eur     = CurrencyCode::fromString('EUR');
        $invoice = Invoice::draft(InvoiceId::fromString(self::INVOICE), ClinicId::fromString(self::CLINIC), null, Payer::create(PayerId::fromString(self::CLIENT), 'Mme Sophie Dubois', []), $eur, $now);
        $invoice->addLine(InvoiceLine::create(
            id: InvoiceLineId::fromString('01960000-0000-7000-8000-00000000e704'),
            label: 'Vermifuge',
            code: null,
            quantity: '1',
            enteredUnitPrice: Money::fromMinorUnits(8870, $eur),
            priceMode: PriceMode::INCLUDING_TAX,
            taxCategoryCode: 'veterinary.drug.companion',
            taxRatePercent: '20',
            operationCategory: OperationCategory::GOODS,
            source: InvoiceLineSource::COUNTER,
            sourceReference: null,
        ), $now);
        $invoice->issue(InvoiceNumber::of(2026, 814), SellerIdentities::complete(), $now);

        $invoices = self::getContainer()->get(InvoiceRepositoryInterface::class);
        \assert($invoices instanceof InvoiceRepositoryInterface);
        $invoices->save($invoice);
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }
}
