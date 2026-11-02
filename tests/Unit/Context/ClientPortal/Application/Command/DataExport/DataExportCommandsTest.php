<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\DataExport;

use App\Context\ClientPortal\Application\Command\BuildDataExport\BuildDataExport;
use App\Context\ClientPortal\Application\Command\BuildDataExport\BuildDataExportHandler;
use App\Context\ClientPortal\Application\Command\DeleteDataExportFile\DeleteDataExportFile;
use App\Context\ClientPortal\Application\Command\DeleteDataExportFile\DeleteDataExportFileHandler;
use App\Context\ClientPortal\Application\Command\DeleteUserDataExports\DeleteUserDataExports;
use App\Context\ClientPortal\Application\Command\DeleteUserDataExports\DeleteUserDataExportsHandler;
use App\Context\ClientPortal\Application\Command\RecordDataExport\RecordDataExport;
use App\Context\ClientPortal\Application\Command\RecordDataExport\RecordDataExportHandler;
use App\Context\ClientPortal\Application\Command\RequestDataExport\RequestDataExport;
use App\Context\ClientPortal\Application\Command\RequestDataExport\RequestDataExportHandler;
use App\Context\ClientPortal\Application\Command\SweepExpiredDataExports\SweepExpiredDataExports;
use App\Context\ClientPortal\Application\Command\SweepExpiredDataExports\SweepExpiredDataExportsHandler;
use App\Context\ClientPortal\Application\Port\OwnerAccount;
use App\Context\ClientPortal\Domain\Exception\DataExportRateLimitedException;
use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportId;
use App\Context\ClientPortal\Domain\Export\DataExportStatus;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalFakes;
use App\Tests\Unit\Context\ClientPortal\Support\DataExportFakes;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryDataExports;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryOwnerProfiles;
use App\Tests\Unit\Context\ClientPortal\Support\SettableClock;
use App\Tests\Unit\System\Notification\Support\InMemoryFiles;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

/**
 * The data export's lifecycle (S5f): request (quota), build outside any
 * transaction, outcome, deletion after 48 hours, nightly sweep, account
 * deletion. The clock is at 2026-11-02 10:00 UTC.
 */
final class DataExportCommandsTest extends TestCase
{
    private const string USER  = ClientPortalFakes::USER;
    private const string OTHER = '01960000-0000-7000-8000-0000000e1002';

    private InMemoryDataExports $exports;
    private DataExportFakes $fakes;
    private ClientPortalFakes $portal;
    private InMemoryOwnerProfiles $profiles;
    private InMemoryFiles $files;
    private SettableClock $clock;

    protected function setUp(): void
    {
        $this->exports  = new InMemoryDataExports();
        $this->fakes    = new DataExportFakes();
        $this->portal   = new ClientPortalFakes();
        $this->profiles = new InMemoryOwnerProfiles();
        $this->files    = new InMemoryFiles();
        $this->clock    = new SettableClock(new \DateTimeImmutable('2026-11-02 10:00:00', new \DateTimeZone('UTC')));
    }

    public function testARequestConsumesTheQuotaAndQueuesTheBuild(): void
    {
        $id = $this->request(self::USER);

        $export = $this->exports->findById($id);
        self::assertNotNull($export);
        self::assertSame([self::USER, DataExportStatus::PENDING, '2026-11-02 10:00'], [$export->userId(), $export->status(), $export->requestedAt()->format('Y-m-d H:i')]);
        self::assertSame([$id], $this->fakes->builds);

        try {
            $this->request(self::USER);
            self::fail('A second export within 24 hours should have been refused.');
        } catch (DataExportRateLimitedException $e) {
            self::assertSame(86_400, $e->retryAfterSeconds);
        }

        self::assertCount(1, $this->exports->exports);
        self::assertNotSame($id, $this->request(self::OTHER), 'the quota is per account');
    }

    public function testABuiltExportIsStoredThenReadyWithItsDeletionQueuedAndAVerifiedEmailNotified(): void
    {
        $id                                = $this->pending();
        $this->fakes->accounts[self::USER] = new OwnerAccount(self::USER, '+33612345678', 'sophie@example.com', true);
        $this->profiles->save(OwnerProfile::createFromClient(self::USER, 'Sophie', 'Martin', null, 'v1', $this->clock->now()));

        $bus = $this->build($id);

        self::assertSame('ZIP of ' . self::USER, $this->files->files['client_portal/exports/' . $id . '.zip']);
        $outcome = self::outcomes($bus);
        self::assertCount(1, $outcome);
        self::assertTrue($outcome[0]->built);

        $this->clock->advance('+2 minutes');
        $this->record($outcome[0]);
        $export = $this->exports->findById($id);
        self::assertSame([DataExportStatus::READY, '2026-11-02 10:02', '2026-11-04 10:02'], [$export?->status(), $export?->readyAt()?->format('Y-m-d H:i'), $export?->expiresAt()?->format('Y-m-d H:i')]);
        self::assertSame([$id], $this->fakes->deletions);

        self::assertCount(1, $this->portal->exportNotices);
        $notice = $this->portal->exportNotices[0];
        self::assertSame([$id, self::USER, 'sophie@example.com', 'Sophie Martin', 'Sophie', '04/11/2026 à 11:02'], [$notice->exportId, $notice->userId, $notice->recipientEmail, $notice->recipientName, $notice->recipientFirstName, $notice->expiresAtLabel]);

        // A replayed outcome or build changes nothing.
        $this->record(new RecordDataExport($id, built: true));
        self::assertSame([[$id], 1], [$this->fakes->deletions, \count($this->portal->exportNotices)]);
        self::assertSame([], self::outcomes($this->build($id)));
    }

    public function testAnAccountWithoutVerifiedEmailOrProfileIsNotNotified(): void
    {
        $unverified                        = $this->pending();
        $this->fakes->accounts[self::USER] = new OwnerAccount(self::USER, '+33612345678', 'sophie@example.com', false);
        $this->record(new RecordDataExport($unverified, built: true));

        $this->fakes->used                 = [];
        $noProfile                         = $this->pending();
        $this->fakes->accounts[self::USER] = new OwnerAccount(self::USER, '+33612345678', 'sophie@example.com', true);
        $this->record(new RecordDataExport($noProfile, built: true));

        self::assertSame(DataExportStatus::READY, $this->exports->findById($unverified)?->status());
        self::assertCount(1, $this->portal->exportNotices);
        self::assertSame(['', ''], [$this->portal->exportNotices[0]->recipientName, $this->portal->exportNotices[0]->recipientFirstName]);
    }

    public function testAFailedBuildIsRecordedAndTheQuotaRefunded(): void
    {
        $id                        = $this->pending();
        $this->fakes->buildFailure = new \RuntimeException('Gotenberg is down');

        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $errors = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->errors[] = (\is_string($level) ? $level : '?') . ': ' . $message;
            }
        };
        $bus = new AnsweringCommandBus(static fn (): null => null);
        new BuildDataExportHandler($this->exports, $this->fakes, $this->files, $bus, $logger)(new BuildDataExport($id));

        self::assertSame(['error: A data export could not be built.'], $logger->errors);
        self::assertSame([], $this->files->files);
        $outcome = self::outcomes($bus);
        self::assertFalse($outcome[0]->built);

        $this->record($outcome[0]);
        self::assertSame([DataExportStatus::FAILED, '2026-11-02 10:00'], [$this->exports->findById($id)?->status(), $this->exports->findById($id)?->failedAt()?->format('Y-m-d H:i')]);
        self::assertSame([self::USER], $this->fakes->refunds);
        self::assertSame([], $this->fakes->deletions);

        $this->record($outcome[0]);
        self::assertSame([self::USER], $this->fakes->refunds, 'refunded once');
        $this->request(self::USER);
    }

    public function testAnOutcomeForAnExportDeletedMeanwhileDeletesItsArchive(): void
    {
        $this->files->files['client_portal/exports/gone.zip'] = 'ZIP';

        $this->record(new RecordDataExport('gone', built: false));
        self::assertSame([], $this->files->deleted);

        $this->record(new RecordDataExport('gone', built: true));
        self::assertSame(['client_portal/exports/gone.zip'], $this->files->deleted);
        self::assertSame([], self::outcomes($this->build('gone')));
    }

    public function testTheDelayedDeletionAndTheSweepRemoveTheArchiveOnce(): void
    {
        $delayed = $this->ready('2026-10-31 09:00');
        $lost    = $this->ready('2026-10-31 08:00');
        $recent  = $this->ready('2026-11-01 12:00');

        new DeleteDataExportFileHandler($this->exports, $this->files)(new DeleteDataExportFile($delayed));
        self::assertSame(['client_portal/exports/' . $delayed . '.zip'], $this->files->deleted);
        self::assertSame(DataExportStatus::EXPIRED, $this->exports->findById($delayed)?->status());

        $sweep = new SweepExpiredDataExportsHandler($this->exports, $this->files, $this->fakes, $this->clock);
        $sweep(new SweepExpiredDataExports());
        self::assertSame(['client_portal/exports/' . $delayed . '.zip', 'client_portal/exports/' . $lost . '.zip'], $this->files->deleted);
        self::assertSame([DataExportStatus::EXPIRED, DataExportStatus::READY], [$this->exports->findById($lost)?->status(), $this->exports->findById($recent)?->status()]);
        self::assertArrayHasKey('client_portal/exports/' . $recent . '.zip', $this->files->files);

        // Replays: the message after the sweep, the deletion of a removed export.
        new DeleteDataExportFileHandler($this->exports, $this->files)(new DeleteDataExportFile($lost));
        new DeleteDataExportFileHandler($this->exports, $this->files)(new DeleteDataExportFile('gone'));
        $sweep(new SweepExpiredDataExports());
        self::assertCount(2, $this->files->deleted);
    }

    public function testTheSweepGivesUpTheExportsStuckPendingOnce(): void
    {
        $stuck                                                          = $this->pending();
        $this->files->files['client_portal/exports/' . $stuck . '.zip'] = 'partial';
        $this->clock->advance('+3 hours');
        $recent = $this->pending(self::OTHER);
        $this->clock->advance('+3 hours 1 minute');

        $sweep = new SweepExpiredDataExportsHandler($this->exports, $this->files, $this->fakes, $this->clock);
        $sweep(new SweepExpiredDataExports());

        self::assertSame(
            [DataExportStatus::FAILED, '2026-11-02 16:01', DataExportStatus::PENDING],
            [$this->exports->findById($stuck)?->status(), $this->exports->findById($stuck)?->failedAt()?->format('Y-m-d H:i'), $this->exports->findById($recent)?->status()],
        );
        self::assertSame(['client_portal/exports/' . $stuck . '.zip'], $this->files->deleted);
        self::assertSame([self::USER], $this->fakes->refunds);

        // Its late outcomes: a failure refunds nothing more, an archive is deleted at once.
        $sweep(new SweepExpiredDataExports());
        $this->record(new RecordDataExport($stuck, built: false));
        self::assertSame([self::USER], $this->fakes->refunds, 'refunded once');
        $this->files->files['client_portal/exports/' . $stuck . '.zip'] = 'ZIP';
        $this->record(new RecordDataExport($stuck, built: true));
        self::assertSame(DataExportStatus::FAILED, $this->exports->findById($stuck)?->status());
        self::assertArrayNotHasKey('client_portal/exports/' . $stuck . '.zip', $this->files->files);
        self::assertSame([], $this->fakes->deletions, 'no delayed deletion queued');
    }

    public function testAccountDeletionRemovesEveryExportOfTheAccountAndItsArchive(): void
    {
        $ready   = $this->ready('2026-11-02 09:00');
        $pending = $this->pending(self::OTHER);
        $this->exports->add(DataExport::request(DataExportId::fromString('01960000-0000-7000-8000-0000000e0099'), self::USER, $this->clock->now()));

        $handler = new DeleteUserDataExportsHandler($this->exports, $this->files);
        $handler(new DeleteUserDataExports(self::USER));

        self::assertSame([$pending], array_keys($this->exports->exports));
        self::assertSame(['client_portal/exports/01960000-0000-7000-8000-0000000e0099.zip', 'client_portal/exports/' . $ready . '.zip'], $this->files->deleted);

        $handler(new DeleteUserDataExports(self::USER));
        self::assertCount(2, $this->files->deleted);
    }

    private function request(string $userId): string
    {
        return new RequestDataExportHandler($this->fakes, $this->exports, $this->fakes, $this->portal, $this->clock)(new RequestDataExport($userId));
    }

    private function pending(string $userId = self::USER): string
    {
        return $this->request($userId);
    }

    /** A ready export, its archive stored, ready at the given instant (UTC). */
    private function ready(string $readyAt): string
    {
        $id     = $this->portal->generate();
        $export = DataExport::request(DataExportId::fromString($id), self::USER, new \DateTimeImmutable($readyAt, new \DateTimeZone('UTC')));
        $export->markReady(new \DateTimeImmutable($readyAt, new \DateTimeZone('UTC')));
        $this->exports->add($export);
        $this->files->files[$export->storageKey()] = 'ZIP';

        return $id;
    }

    private function build(string $exportId): AnsweringCommandBus
    {
        $bus = new AnsweringCommandBus(static fn (): null => null);
        new BuildDataExportHandler($this->exports, $this->fakes, $this->files, $bus, new NullLogger())(new BuildDataExport($exportId));

        return $bus;
    }

    private function record(RecordDataExport $command): void
    {
        new RecordDataExportHandler($this->exports, $this->fakes, $this->fakes, $this->fakes, $this->profiles, $this->portal, $this->files, $this->clock)($command);
    }

    /**
     * @return list<RecordDataExport>
     */
    private static function outcomes(AnsweringCommandBus $bus): array
    {
        return array_values(array_filter($bus->commands, static fn (object $command): bool => $command instanceof RecordDataExport));
    }
}
