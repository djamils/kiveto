<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Messaging;

use App\Context\ClientPortal\Application\Command\BuildDataExport\BuildDataExport;
use App\Context\ClientPortal\Application\Command\DeleteDataExportFile\DeleteDataExportFile;
use App\Context\ClientPortal\Infrastructure\Messaging\MessengerDataExportJobs;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class MessengerDataExportJobsTest extends TestCase
{
    public function testTheBuildGoesThroughTheExternalQueueAndTheDeletionWaits48Hours(): void
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
        $jobs = new MessengerDataExportJobs($external, $commands);

        $jobs->queueBuild('export-1');
        $jobs->deleteFileLater('export-1');

        $build = $external->sent[0];
        self::assertInstanceOf(BuildDataExport::class, $build);
        self::assertSame('export-1', $build->exportId);

        $envelope = $commands->dispatched[0];
        $delete   = $envelope->getMessage();
        self::assertInstanceOf(DeleteDataExportFile::class, $delete);
        self::assertSame('export-1', $delete->exportId);
        self::assertSame(48 * 3600 * 1000, $envelope->last(DelayStamp::class)?->getDelay());
    }
}
