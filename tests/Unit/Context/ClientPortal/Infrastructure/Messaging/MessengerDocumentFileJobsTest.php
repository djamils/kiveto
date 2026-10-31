<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Messaging;

use App\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile\DeleteStoredDocumentFile;
use App\Context\ClientPortal\Application\Command\StorePublishedDocumentFile\StorePublishedDocumentFile;
use App\Context\ClientPortal\Infrastructure\Messaging\MessengerDocumentFileJobs;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class MessengerDocumentFileJobsTest extends TestCase
{
    public function testTheFileIsStoredThroughTheExternalQueueAndTheOldOneDeletedTenMinutesLater(): void
    {
        $external = new class implements ExternalApiCommandBusInterface {
            /** @var list<ExternalApiCommandInterface> */
            public array $sent = [];

            public function send(ExternalApiCommandInterface $command, object ...$stamps): void
            {
                $this->sent[] = $command;
            }
        };
        $commands = new class implements MessageBusInterface {
            /** @var list<Envelope> */
            public array $dispatched = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->dispatched[] = Envelope::wrap($message, $stamps);
            }
        };
        $jobs = new MessengerDocumentFileJobs($external, $commands);

        $jobs->storeFile('doc-1', 3);
        $jobs->deleteFileLater('portal/doc-1/v2.pdf');

        $store = $external->sent[0];
        self::assertInstanceOf(StorePublishedDocumentFile::class, $store);
        self::assertSame(['doc-1', 3], [$store->documentId, $store->version]);

        $envelope = $commands->dispatched[0];
        $delete   = $envelope->getMessage();
        self::assertInstanceOf(DeleteStoredDocumentFile::class, $delete);
        self::assertSame('portal/doc-1/v2.pdf', $delete->storageKey);
        self::assertSame(600_000, $envelope->last(DelayStamp::class)?->getDelay());
    }
}
