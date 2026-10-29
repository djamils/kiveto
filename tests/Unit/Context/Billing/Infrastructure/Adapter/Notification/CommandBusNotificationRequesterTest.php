<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter\Notification;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Port\InvoiceMessage;
use App\Context\Billing\Infrastructure\Adapter\Notification\CommandBusNotificationRequester;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RequestNotification\AttachmentReference;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use PHPUnit\Framework\TestCase;

final class CommandBusNotificationRequesterTest extends TestCase
{
    public function testAnInvoiceMessageBecomesANotificationRequest(): void
    {
        $bus       = $this->bus();
        $requester = new CommandBusNotificationRequester($bus);

        $id = $requester->request(new InvoiceMessage(
            purpose: InvoiceMessage::SENT,
            channel: 'EMAIL',
            recipientAddress: 's.dubois@email.fr',
            recipientName: 'Sophie Dubois',
            clinicId: 'clinic-1',
            clientId: 'client-1',
            invoiceId: 'invoice-1',
            idempotencyKey: 'invoice:invoice-1:send:d-1',
            parameters: ['invoiceNumber' => 'FAC-1'],
            attachment: new SentDocumentFile('billing/sent/invoice/invoice-1.pdf', 'FAC-1.pdf'),
        ));
        $requester->request(new InvoiceMessage(
            purpose: InvoiceMessage::REMINDER,
            channel: 'SMS',
            recipientAddress: '+33612345678',
            recipientName: 'Sophie Dubois',
            clinicId: 'clinic-1',
            clientId: 'client-1',
            invoiceId: 'invoice-1',
            idempotencyKey: 'invoice:invoice-1:reminder:d-2:sms',
            parameters: ['invoiceNumber' => 'FAC-1'],
            fallbackAddress: 's.dubois@email.fr',
            fallbackParameters: ['addressee' => 'Madame Dubois'],
        ));

        self::assertSame('message-id', $id);
        [$sent, $reminder] = $bus->commands;
        self::assertInstanceOf(RequestNotification::class, $sent);
        self::assertSame(['INVOICE_SENT', 'EMAIL', 's.dubois@email.fr', 'Sophie Dubois', 'clinic-1', 'client-1', 'invoice', 'invoice-1', 'invoice:invoice-1:send:d-1', ['invoiceNumber' => 'FAC-1']], [
            $sent->purpose, $sent->channel, $sent->recipientAddress, $sent->recipientName, $sent->clinicId, $sent->clientId,
            $sent->subjectType, $sent->subjectId, $sent->idempotencyKey, $sent->parameters,
        ]);
        self::assertSame(['billing/sent/invoice/invoice-1.pdf', 'FAC-1.pdf', 'application/pdf', AttachmentReference::OWNER_REQUESTER], [
            $sent->attachments[0]->storageKey, $sent->attachments[0]->filename, $sent->attachments[0]->mime, $sent->attachments[0]->owner,
        ]);
        self::assertNull($sent->fallback);

        self::assertInstanceOf(RequestNotification::class, $reminder);
        self::assertSame([], $reminder->attachments);
        self::assertSame(['s.dubois@email.fr', 'Sophie Dubois', ['addressee' => 'Madame Dubois']], [$reminder->fallback?->address, $reminder->fallback?->name, $reminder->fallback?->parameters]);
    }

    /**
     * @return CommandBusInterface&object{commands: list<object>}
     */
    private function bus(): CommandBusInterface
    {
        return new class implements CommandBusInterface {
            /** @var list<object> */
            public array $commands = [];

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->commands[] = $command;

                return 'message-id';
            }
        };
    }
}
