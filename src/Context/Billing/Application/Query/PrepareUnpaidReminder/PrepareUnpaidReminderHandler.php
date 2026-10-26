<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\PrepareUnpaidReminder;

use App\Context\Billing\Application\Dispatch\InvoiceMessages;
use App\Context\Billing\Application\Dispatch\ReminderCandidate;
use App\Context\Billing\Application\Dispatch\UnpaidReminderSelection;
use App\Context\Billing\Application\Port\ClientContact;
use App\Context\Billing\Application\Port\SmsSegmentEstimatorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Counts the clients and the SMS segments of a reminder. Every SMS of the
 * template differs only by its number, date and amount, all GSM-7: SMS of
 * the same length take the same segments, so one estimate per length is
 * asked to Notification.
 */
#[AsMessageHandler]
final readonly class PrepareUnpaidReminderHandler
{
    public function __construct(
        private UnpaidReminderSelection $selection,
        private SmsSegmentEstimatorInterface $segments,
    ) {
    }

    public function __invoke(PrepareUnpaidReminder $query): UnpaidReminderPreview
    {
        [$candidates, $skipped] = $this->selection->select($query->clinicId, $query->invoiceIds);

        $bySms    = array_values(array_filter($candidates, static fn (ReminderCandidate $c): bool => $c->sendsOn(ClientContact::SMS)));
        $byLength = [];
        $segments = 0;

        foreach ($bySms as $candidate) {
            $parameters = InvoiceMessages::reminderSmsParameters($candidate->invoice->number, $candidate->invoice->issuedAt, $candidate->invoice->balanceDueMinorUnits);
            $length     = mb_strlen(implode('', $parameters));
            $segments += $byLength[$length] ??= $this->segments->reminderSegments($query->clinicId, $parameters);
        }

        return new UnpaidReminderPreview(
            invoiceIds: array_map(static fn (ReminderCandidate $c): string => $c->invoice->id, $candidates),
            clientCount: \count(array_unique(array_map(static fn (ReminderCandidate $c): string => $c->contact->clientId, $candidates))),
            smsCount: \count($bySms),
            smsSegments: $segments,
            skippedCount: \count($skipped),
        );
    }
}
