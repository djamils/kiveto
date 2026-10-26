<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Dispatch;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Dispatch\InvoiceLabels;
use App\Context\Billing\Application\Dispatch\InvoiceMessages;
use App\Context\Billing\Application\Port\VisitSummary;
use App\Context\Billing\Domain\Dispatch\DispatchKind;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Tests\Unit\Context\Billing\Support\FakeClientContacts;
use App\Tests\Unit\Context\Billing\Support\Invoices;
use PHPUnit\Framework\TestCase;

final class InvoiceMessagesTest extends TestCase
{
    private const string DISPATCH = '01960000-0000-7000-8000-00000000e101';

    public function testTheInvoiceEmailWithABalanceDue(): void
    {
        $file    = new SentDocumentFile('billing/sent/invoice/x.pdf', 'FAC-2026-000814.pdf');
        $message = new InvoiceMessages()->sent($this->dispatch(['EMAIL']), Invoices::issued(due: 4000), FakeClientContacts::contact(), $this->visit('Minou'), $file);

        self::assertSame(['INVOICE_SENT', 'EMAIL', 's.dubois@email.fr', 'Sophie Dubois'], [$message->purpose, $message->channel, $message->recipientAddress, $message->recipientName]);
        self::assertSame([Invoices::CLINIC, Invoices::CLIENT, Invoices::INVOICE], [$message->clinicId, $message->clientId, $message->invoiceId]);
        self::assertSame('invoice:' . Invoices::INVOICE . ':send:' . self::DISPATCH, $message->idempotencyKey);
        self::assertSame($file, $message->attachment);
        self::assertSame([
            'addressee'     => 'Madame Dubois',
            'invoiceNumber' => 'FAC-2026-000814',
            'invoiceDate'   => '16/10/2026',
            'purchaseLabel' => 'la consultation de Minou du 16 octobre 2026',
            'vatLabels'     => ['20 %'],
            'vatAmounts'    => ['14,78 €'],
            'total'         => '88,70 €',
            'balanceDue'    => '40,00 €',
        ], $message->parameters);
    }

    public function testASettledInvoiceAndAVisitWithoutAnimal(): void
    {
        $message = new InvoiceMessages()->sent($this->dispatch(['EMAIL']), Invoices::issued(status: 'PAID', due: 0), FakeClientContacts::contact(), $this->visit(null), new SentDocumentFile('k', 'f.pdf'));

        self::assertSame('', $message->parameters['balanceDue']);
        self::assertSame('la consultation du 16 octobre 2026', $message->parameters['purchaseLabel']);
    }

    public function testACounterSaleIsAPurchase(): void
    {
        $message = new InvoiceMessages()->sent($this->dispatch(['EMAIL']), Invoices::issued(admission: null, issuedAt: null), FakeClientContacts::contact(), null, new SentDocumentFile('k', 'f.pdf'));

        // Created 15/10 22:30 UTC: 16/10 in Paris.
        self::assertSame('vos achats du 16/10/2026', $message->parameters['purchaseLabel']);
        self::assertSame('16/10/2026', $message->parameters['invoiceDate']);
    }

    public function testADraftIsNeverSent(): void
    {
        $this->expectException(\LogicException::class);
        new InvoiceMessages()->sent($this->dispatch(['EMAIL']), Invoices::issued(number: null), FakeClientContacts::contact(), null, new SentDocumentFile('k', 'f.pdf'));
    }

    public function testTheReminderEmailCarriesThePdf(): void
    {
        $file    = new SentDocumentFile('k', 'f.pdf');
        $message = new InvoiceMessages()->reminderEmail($this->dispatch(['EMAIL', 'SMS']), Invoices::issued(total: 6450, due: 2450), FakeClientContacts::contact(), $this->visit('Cookie'), $file);

        self::assertSame(['INVOICE_REMINDER', 'EMAIL', 'invoice:' . Invoices::INVOICE . ':reminder:' . self::DISPATCH . ':email'], [$message->purpose, $message->channel, $message->idempotencyKey]);
        self::assertSame($file, $message->attachment);
        self::assertSame([
            'addressee'     => 'Madame Dubois',
            'invoiceNumber' => 'FAC-2026-000814',
            'invoiceDate'   => '16/10/2026',
            'careLabel'     => 'des soins de Cookie',
            'total'         => '64,50 €',
            'paid'          => '40,00 €',
            'balanceDue'    => '24,50 €',
        ], $message->parameters);
    }

    public function testCareLabelsWithoutAnimalOrVisit(): void
    {
        $messages = new InvoiceMessages();

        self::assertSame('des soins du 16/10/2026', $messages->reminderEmail($this->dispatch(['EMAIL']), Invoices::issued(), FakeClientContacts::contact(), $this->visit(null), new SentDocumentFile('k', 'f.pdf'))->parameters['careLabel']);
        self::assertSame('de vos achats du 16/10/2026', $messages->reminderEmail($this->dispatch(['EMAIL']), Invoices::issued(admission: null), FakeClientContacts::contact(), null, new SentDocumentFile('k', 'f.pdf'))->parameters['careLabel']);
    }

    public function testTheReminderSmsFallsBackOnEmailOnlyWhenEmailIsNotAlreadyAChannel(): void
    {
        $messages = new InvoiceMessages();
        $alone    = $messages->reminderSms($this->dispatch(['SMS']), Invoices::issued(due: 6450), FakeClientContacts::contact(), $this->visit('Cookie'));

        self::assertSame(['INVOICE_REMINDER', 'SMS', '+33612345678', 'invoice:' . Invoices::INVOICE . ':reminder:' . self::DISPATCH . ':sms'], [$alone->purpose, $alone->channel, $alone->recipientAddress, $alone->idempotencyKey]);
        self::assertSame(['invoiceNumber' => 'FAC-2026-000814', 'shortDate' => '16/10', 'balanceDue' => '64,50 €'], $alone->parameters);
        self::assertNull($alone->attachment);
        self::assertSame('s.dubois@email.fr', $alone->fallbackAddress);
        self::assertSame('des soins de Cookie', $alone->fallbackParameters['careLabel'] ?? null);

        $both = $messages->reminderSms($this->dispatch(['EMAIL', 'SMS']), Invoices::issued(), FakeClientContacts::contact(), null);
        self::assertNull($both->fallbackAddress);
        self::assertNull($both->fallbackParameters);

        $noEmail = $messages->reminderSms($this->dispatch(['SMS']), Invoices::issued(), FakeClientContacts::contact(email: null), null);
        self::assertNull($noEmail->fallbackAddress);
    }

    public function testLabels(): void
    {
        self::assertSame('1 204,05 €', InvoiceLabels::euros(120405));
        self::assertSame('5,5 %', InvoiceLabels::rate('5.50'));
        self::assertSame('20 %', InvoiceLabels::rate('20'));
        self::assertSame('0 %', InvoiceLabels::rate('0.00'));
        self::assertSame('1 janvier 2027', InvoiceLabels::longDate(new \DateTimeImmutable('2026-12-31 23:30:00', new \DateTimeZone('UTC'))));
    }

    /**
     * @param list<string> $channels
     */
    private function dispatch(array $channels): InvoiceDispatch
    {
        return InvoiceDispatch::log(InvoiceDispatchId::fromString(self::DISPATCH), ClinicId::fromString(Invoices::CLINIC), InvoiceId::fromString(Invoices::INVOICE), DispatchKind::REMINDER, $channels, null, new \DateTimeImmutable());
    }

    private function visit(?string $animal): VisitSummary
    {
        return new VisitSummary($animal, new \DateTimeImmutable('2026-10-16 07:45:00', new \DateTimeZone('UTC')));
    }
}
