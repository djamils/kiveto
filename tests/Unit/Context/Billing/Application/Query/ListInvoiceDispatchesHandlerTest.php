<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Query;

use App\Context\Billing\Application\Port\DeliveryStatus;
use App\Context\Billing\Application\Port\DeliveryStatusReaderInterface;
use App\Context\Billing\Application\Query\ListInvoiceDispatches\ListInvoiceDispatches;
use App\Context\Billing\Application\Query\ListInvoiceDispatches\ListInvoiceDispatchesHandler;
use App\Context\Billing\Domain\Dispatch\DispatchKind;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Billing\Support\InMemoryDispatches;
use App\Tests\Unit\Context\Billing\Support\Invoices;
use PHPUnit\Framework\TestCase;

final class ListInvoiceDispatchesHandlerTest extends TestCase
{
    public function testTheHistoryTellsWhereEachMessageStands(): void
    {
        $dispatches = new InMemoryDispatches();
        $this->log($dispatches, '01960000-0000-7000-8000-00000000e601', DispatchKind::SEND, ['EMAIL'], '2026-10-20 09:00:00', ['m-1']);
        $this->log($dispatches, '01960000-0000-7000-8000-00000000e602', DispatchKind::REMINDER, ['EMAIL', 'SMS'], '2026-10-24 09:00:00', ['m-2', 'm-3']);
        $this->log($dispatches, '01960000-0000-7000-8000-00000000e603', DispatchKind::REMINDER, ['SMS'], '2026-10-25 23:30:00', []);

        $reader = new class implements DeliveryStatusReaderInterface {
            /** @var list<list<string>> */
            public array $asked = [];

            public function statuses(array $messageIds): array
            {
                $this->asked[] = $messageIds;

                return [
                    'm-1' => new DeliveryStatus('EMAIL', 'BOUNCED', 'Non délivré'),
                    'm-3' => new DeliveryStatus('SMS', 'DELIVERED', 'Délivré'),
                ];
            }
        };

        $history = $this->handler($dispatches, $reader)(new ListInvoiceDispatches(Invoices::CLINIC, Invoices::INVOICE));

        self::assertSame([['m-1', 'm-2', 'm-3']], $reader->asked, 'One query for every message.');
        self::assertSame(2, $history->reminderCount);
        self::assertTrue($history->remindedToday, 'Paris: 26/10 00:30.');
        [$send, $first, $second] = $history->dispatches;
        self::assertSame(['SEND', ['EMAIL'], 'author'], [$send->kind, $send->channels, $send->authorUserId]);
        self::assertSame('Non délivré', $send->deliveries[0]->label);
        self::assertSame(['Délivré'], array_map(static fn (DeliveryStatus $d): string => $d->label, $first->deliveries), 'An unknown message is left out.');
        self::assertSame([], $second->deliveries);
        self::assertSame('2026-10-25 23:30', $second->at->format('Y-m-d H:i'));
    }

    public function testAnInvoiceNeverSent(): void
    {
        $reader = new class implements DeliveryStatusReaderInterface {
            public function statuses(array $messageIds): array
            {
                throw new \LogicException('Nothing to ask.');
            }
        };

        $history = $this->handler(new InMemoryDispatches(), $reader)(new ListInvoiceDispatches(Invoices::CLINIC, Invoices::INVOICE));

        self::assertSame([[], 0, false], [$history->dispatches, $history->reminderCount, $history->remindedToday]);
    }

    private function handler(InMemoryDispatches $dispatches, DeliveryStatusReaderInterface $reader): ListInvoiceDispatchesHandler
    {
        return new ListInvoiceDispatchesHandler($dispatches, $reader, new FrozenClock(new \DateTimeImmutable('2026-10-26 10:00:00', new \DateTimeZone('UTC'))));
    }

    /**
     * @param list<string> $channels
     * @param list<string> $messages
     */
    private function log(InMemoryDispatches $dispatches, string $id, DispatchKind $kind, array $channels, string $at, array $messages): void
    {
        $dispatch = InvoiceDispatch::log(InvoiceDispatchId::fromString($id), ClinicId::fromString(Invoices::CLINIC), InvoiceId::fromString(Invoices::INVOICE), $kind, $channels, 'author', new \DateTimeImmutable($at, new \DateTimeZone('UTC')));

        if ([] !== $messages) {
            $dispatch->recordNotifications($messages);
        }

        $dispatches->save($dispatch);
    }
}
