<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Dispatch;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Port\ClientContact;
use App\Context\Billing\Application\Port\InvoiceMessage;
use App\Context\Billing\Application\Port\VisitSummary;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;

/**
 * Composes the invoice messages with exactly the parameters Notification's
 * templates declare (INVOICE_SENT e-mail, INVOICE_REMINDER e-mail and SMS).
 * Amounts are the invoice's current ones: the stored PDF is the invoice as
 * first sent, the message always tells what is left to pay now.
 */
final class InvoiceMessages
{
    public function sent(InvoiceDispatch $dispatch, InvoiceDetails $invoice, ClientContact $contact, ?VisitSummary $visit, SentDocumentFile $file): InvoiceMessage
    {
        \assert(null !== $contact->email);

        $vatLabels  = [];
        $vatAmounts = [];

        foreach ($invoice->taxBreakdown as $rate) {
            if ($rate['taxMinorUnits'] > 0) {
                $vatLabels[]  = InvoiceLabels::rate($rate['ratePercent']);
                $vatAmounts[] = InvoiceLabels::euros($rate['taxMinorUnits']);
            }
        }

        $due = max(0, $invoice->balanceDueMinorUnits);

        return new InvoiceMessage(
            purpose: InvoiceMessage::SENT,
            channel: ClientContact::EMAIL,
            recipientAddress: $contact->email,
            recipientName: $contact->name,
            clinicId: $invoice->clinicId,
            clientId: $contact->clientId,
            invoiceId: $invoice->id,
            idempotencyKey: \sprintf('invoice:%s:send:%s', $invoice->id, $dispatch->id()->toString()),
            parameters: [
                'addressee'     => $contact->addressee,
                'invoiceNumber' => self::number($invoice),
                'invoiceDate'   => InvoiceLabels::date(self::issuedAt($invoice)),
                'purchaseLabel' => self::purchase($invoice, $visit),
                'vatLabels'     => $vatLabels,
                'vatAmounts'    => $vatAmounts,
                'total'         => InvoiceLabels::euros($invoice->totalGrossMinorUnits),
                'balanceDue'    => $due > 0 ? InvoiceLabels::euros($due) : '',
            ],
            attachment: $file,
        );
    }

    /** The reminder e-mail, with the invoice PDF. */
    public function reminderEmail(InvoiceDispatch $dispatch, InvoiceDetails $invoice, ClientContact $contact, ?VisitSummary $visit, SentDocumentFile $file): InvoiceMessage
    {
        \assert(null !== $contact->email);

        return new InvoiceMessage(
            purpose: InvoiceMessage::REMINDER,
            channel: ClientContact::EMAIL,
            recipientAddress: $contact->email,
            recipientName: $contact->name,
            clinicId: $invoice->clinicId,
            clientId: $contact->clientId,
            invoiceId: $invoice->id,
            idempotencyKey: self::reminderKey($dispatch, $invoice, 'email'),
            parameters: self::reminderEmailParameters($invoice, $contact, $visit),
            attachment: $file,
        );
    }

    /**
     * The reminder SMS (non-critical). When the clinic's SMS cap holds it
     * back, Notification sends the e-mail instead — only when e-mail is not
     * already one of the reminder's channels, so the client never gets two.
     */
    public function reminderSms(InvoiceDispatch $dispatch, InvoiceDetails $invoice, ClientContact $contact, ?VisitSummary $visit): InvoiceMessage
    {
        \assert(null !== $contact->mobile);

        $fallback = !$dispatch->sendsOn(ClientContact::EMAIL) && null !== $contact->email;

        return new InvoiceMessage(
            purpose: InvoiceMessage::REMINDER,
            channel: ClientContact::SMS,
            recipientAddress: $contact->mobile,
            recipientName: $contact->name,
            clinicId: $invoice->clinicId,
            clientId: $contact->clientId,
            invoiceId: $invoice->id,
            idempotencyKey: self::reminderKey($dispatch, $invoice, 'sms'),
            parameters: self::reminderSmsParameters($invoice),
            fallbackAddress: $fallback ? $contact->email : null,
            fallbackParameters: $fallback ? self::reminderEmailParameters($invoice, $contact, $visit) : null,
        );
    }

    /**
     * The parameters of the reminder SMS, also used to estimate the SMS
     * segments of a bulk reminder.
     *
     * @return array<string, string>
     */
    public static function reminderSmsParameters(InvoiceDetails $invoice): array
    {
        return [
            'invoiceNumber' => self::number($invoice),
            'shortDate'     => InvoiceLabels::shortDate(self::issuedAt($invoice)),
            'balanceDue'    => InvoiceLabels::euros(max(0, $invoice->balanceDueMinorUnits)),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function reminderEmailParameters(InvoiceDetails $invoice, ClientContact $contact, ?VisitSummary $visit): array
    {
        $due = max(0, $invoice->balanceDueMinorUnits);

        return [
            'addressee'     => $contact->addressee,
            'invoiceNumber' => self::number($invoice),
            'invoiceDate'   => InvoiceLabels::date(self::issuedAt($invoice)),
            'careLabel'     => self::care($invoice, $visit),
            'total'         => InvoiceLabels::euros($invoice->totalGrossMinorUnits),
            'paid'          => InvoiceLabels::euros(max(0, $invoice->totalGrossMinorUnits - $due)),
            'balanceDue'    => InvoiceLabels::euros($due),
        ];
    }

    private static function reminderKey(InvoiceDispatch $dispatch, InvoiceDetails $invoice, string $channel): string
    {
        return \sprintf('invoice:%s:reminder:%s:%s', $invoice->id, $dispatch->id()->toString(), $channel);
    }

    /** « la consultation de Minou du 16 octobre 2026 », « vos achats du 16/10/2026 ». */
    private static function purchase(InvoiceDetails $invoice, ?VisitSummary $visit): string
    {
        if (null === $visit) {
            return 'vos achats du ' . InvoiceLabels::date(self::issuedAt($invoice));
        }

        return null !== $visit->animalName
            ? \sprintf('la consultation de %s du %s', $visit->animalName, InvoiceLabels::longDate($visit->openedAt))
            : 'la consultation du ' . InvoiceLabels::longDate($visit->openedAt);
    }

    /** « la facture des soins de Cookie », « la facture de vos achats du 04/09/2026 ». */
    private static function care(InvoiceDetails $invoice, ?VisitSummary $visit): string
    {
        if (null === $visit) {
            return 'de vos achats du ' . InvoiceLabels::date(self::issuedAt($invoice));
        }

        return null !== $visit->animalName
            ? 'des soins de ' . $visit->animalName
            : 'des soins du ' . InvoiceLabels::date($visit->openedAt);
    }

    private static function number(InvoiceDetails $invoice): string
    {
        return $invoice->number ?? throw new \LogicException('Only an issued invoice is sent.');
    }

    private static function issuedAt(InvoiceDetails $invoice): \DateTimeImmutable
    {
        return $invoice->issuedAt ?? $invoice->createdAt;
    }
}
