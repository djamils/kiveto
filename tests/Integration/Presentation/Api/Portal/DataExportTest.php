<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\ClientPortal\Application\Command\DeleteDataExportFile\DeleteDataExportFile;
use App\Context\ClientPortal\Application\Command\DeleteDataExportFile\DeleteDataExportFileHandler;
use App\Context\ClientPortal\Application\Command\DeleteUserDataExports\DeleteUserDataExports;
use App\Context\ClientPortal\Application\Command\SweepExpiredDataExports\SweepExpiredDataExports;
use App\Context\ClientPortal\Application\Command\SweepExpiredDataExports\SweepExpiredDataExportsHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Pdf\PdfRendererInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Support\Document\RecordingPdfRenderer;
use App\Tests\Support\MessengerDrain;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 5: the data export (AC 63, 91, 209, 210, 212). The build runs on
 * the external queue, drained in the test; the PDF service is a recorder.
 * The clocks start on 2 November 2026, 10:00 UTC.
 */
final class DataExportTest extends PetsApiTestCase
{
    private RecordingPdfRenderer $pdf;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.93';
        parent::setUp();
        $this->pdf = new RecordingPdfRenderer();
        $this->browser->getContainer()->set(PdfRendererInterface::class, $this->pdf);
    }

    public function testAnExportIsBuiltAsynchronouslyAndDownloadedThroughAFiveMinuteSignedUrl(): void
    {
        $this->document(DocumentType::REPORT, self::CLIENT, '2026-10-20 08:30:00', []);

        $request = self::json($this->authPost('/me/data-export'), 202);
        $id      = self::string($request['id'] ?? null);
        self::assertSame(['id' => $id, 'status' => 'pending'], $request);
        self::assertSame([['id' => $id, 'status' => 'pending', 'requestedAt' => '2026-11-02T10:00:00+00:00', 'readyAt' => null, 'expiresAt' => null]], self::list($this->authGet('/me/data-exports')));
        self::assertSame('not_found', self::problemType($this->authGet('/me/data-exports/' . $id . '/file'), 404), 'not built yet');

        $this->drain();

        self::assertSame([['id' => $id, 'status' => 'ready', 'requestedAt' => '2026-11-02T10:00:00+00:00', 'readyAt' => '2026-11-02T10:00:00+00:00', 'expiresAt' => '2026-11-04T10:00:00+00:00']], self::list($this->authGet('/me/data-exports')));
        self::assertStringContainsString('Export de vos données', (string) $this->pdf->request?->html);
        self::assertSame([], $this->notices(), 'no verified e-mail, no notice');

        $redirect = $this->authGet('/me/data-exports/' . $id . '/file');
        self::assertSame(302, $redirect->getStatusCode());
        self::assertStringContainsString('no-store', (string) $redirect->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $redirect->headers->get('Cache-Control'));
        $url = (string) $redirect->headers->get('Location');
        self::assertMatchesRegularExpression('#/api/portal/v1/files/exports/' . $id . '\?#', $url);

        $zip = $this->download($url);
        self::assertSame(200, $zip->getStatusCode());
        self::assertSame('application/zip', $zip->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', (string) $zip->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $zip->headers->get('Cache-Control'));
        $files = self::unzip((string) $zip->getContent());
        self::assertSame(['account.json', 'clinics/' . self::CLINIC . '.json', 'summary.pdf'], array_keys($files));
        $account = json_decode($files['account.json'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($account);
        self::assertSame([1, $this->phone()], [$account['schemaVersion'], \is_array($account['account']) ? $account['account']['phone'] : null]);
        $clinic = json_decode($files['clinics/' . self::CLINIC . '.json'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($clinic);
        self::assertSame(['id' => self::CLINIC, 'name' => 'Clinique des Lilas', 'city' => null], $clinic['clinic']);
        self::assertSame([['type' => 'report', 'title' => 'Compte rendu du 20/10/2026', 'issuedAt' => '2026-10-20T10:30:00+02:00']], $clinic['documents']);
        self::assertSame('%PDF', $files['summary.pdf']);

        // The archive is deleted 48 hours after it is ready (available_at is
        // stored to the second, rounded up by Messenger: never early).
        $deletions = $this->scheduledDeletions();
        self::assertCount(1, $deletions);
        self::assertContains($deletions[0], [172_800, 172_801]);

        self::assertSame('not_found', self::problemType($this->download(substr($url, 0, -4) . 'abcd'), 404));
        self::assertSame('not_found', self::problemType($this->download(strtok($url, '?') ?: $url), 404));
        self::assertSame('not_found', self::problemType($this->authGet('/me/data-exports/' . Uuid::v7()->toRfc4122() . '/file'), 404));

        self::mockTime('+6 minutes');
        self::assertSame('not_found', self::problemType($this->download($url), 404), 'the signed URL lasts 5 minutes');

        $limited = $this->authPost('/me/data-export');
        self::assertSame('export_rate_limited', self::problemType($limited, 429));
        self::assertGreaterThan(0, (int) $limited->headers->get('Retry-After'));
    }

    public function testAVerifiedEmailReceivesANoticeWithoutLink(): void
    {
        $this->connection()->executeStatement(
            'UPDATE identity_access__users SET email = ?, email_verified_at = ? WHERE id = ?',
            ['sophie@example.com', '2026-10-01 08:00:00', Uuid::fromString($this->userId)->toBinary()],
        );

        $id = self::string(self::json($this->authPost('/me/data-export'), 202)['id'] ?? null);
        $this->drain();

        $notices = $this->notices();
        self::assertCount(1, $notices);
        self::assertSame(['EMAIL', 'sophie@example.com', $id, null], [$notices[0]->channel, $notices[0]->recipientAddress, $notices[0]->subjectId, $notices[0]->clinicId]);
        self::assertSame(['ownerFirstName' => '', 'expiresAtLabel' => '04/11/2026 à 11:00'], $notices[0]->parameters);
    }

    public function testAfter48HoursTheFileRouteAnswersNotFoundAndTheArchiveIsGone(): void
    {
        $id = self::string(self::json($this->authPost('/me/data-export'), 202)['id'] ?? null);
        $this->drain();
        $key = 'client_portal/exports/' . $id . '.zip';
        self::assertNotSame('', $this->storage()->read($key));

        $this->travelTo('2026-11-04 10:00:00');
        self::assertSame('not_found', self::problemType($this->authGet('/me/data-exports/' . $id . '/file'), 404));
        self::assertSame('expired', self::list($this->authGet('/me/data-exports'))[0]['status']);

        // The delayed deletion was lost: the nightly sweep deletes the archive.
        $this->service(SweepExpiredDataExportsHandler::class)(new SweepExpiredDataExports());
        self::assertFalse($this->stored($key));
        self::assertSame('expired', self::list($this->authGet('/me/data-exports'))[0]['status']);

        // The delayed message arriving later changes nothing.
        $this->service(DeleteDataExportFileHandler::class)(new DeleteDataExportFile($id));
        self::assertSame('expired', self::list($this->authGet('/me/data-exports'))[0]['status']);
    }

    public function testTheDelayedDeletionRemovesTheArchive(): void
    {
        $id = self::string(self::json($this->authPost('/me/data-export'), 202)['id'] ?? null);
        $this->drain();

        $this->travelTo('2026-11-04 10:00:00');
        $this->service(DeleteDataExportFileHandler::class)(new DeleteDataExportFile($id));

        self::assertFalse($this->stored('client_portal/exports/' . $id . '.zip'));
        self::assertSame('expired', self::list($this->authGet('/me/data-exports'))[0]['status']);
    }

    public function testAFailedBuildRefundsTheQuota(): void
    {
        $this->pdf->fail = true;
        self::json($this->authPost('/me/data-export'), 202);
        $this->drain();

        self::assertSame('failed', self::list($this->authGet('/me/data-exports'))[0]['status']);

        $this->pdf->fail = false;
        $retry           = self::string(self::json($this->authPost('/me/data-export'), 202)['id'] ?? null);
        $this->drain();
        self::assertSame([$retry, 'ready'], [self::list($this->authGet('/me/data-exports'))[0]['id'], self::list($this->authGet('/me/data-exports'))[0]['status']]);
    }

    public function testAnAccountWithoutLinkGetsTheAccountAndTheSummaryOnly(): void
    {
        $this->connection()->executeStatement('DELETE FROM client_portal__client_links WHERE user_id = ?', [Uuid::fromString($this->userId)->toBinary()]);

        $id = self::string(self::json($this->authPost('/me/data-export'), 202)['id'] ?? null);
        $this->drain();

        $files = self::unzip($this->storage()->read('client_portal/exports/' . $id . '.zip'));
        self::assertSame(['account.json', 'summary.pdf'], array_keys($files));
    }

    public function testAccountDeletionRemovesTheExportAndItsFile(): void
    {
        $id = self::string(self::json($this->authPost('/me/data-export'), 202)['id'] ?? null);
        $this->drain();
        $url = (string) $this->authGet('/me/data-exports/' . $id . '/file')->headers->get('Location');

        $this->commands()->dispatch(new DeleteUserDataExports($this->userId));

        self::assertSame([], self::list($this->authGet('/me/data-exports')));
        self::assertSame('not_found', self::problemType($this->authGet('/me/data-exports/' . $id . '/file'), 404));
        self::assertSame('not_found', self::problemType($this->download($url), 404));
        self::assertFalse($this->stored('client_portal/exports/' . $id . '.zip'));
    }

    public function testAnotherAccountsExportIsNotFound(): void
    {
        $id = self::string(self::json($this->authPost('/me/data-export'), 202)['id'] ?? null);
        $this->drain();

        PortalUserFactory::new()->withPhone('+33615000394')->create();
        $this->token = self::string($this->signIn('+33615000394')['accessToken']);
        self::assertSame('not_found', self::problemType($this->authGet('/me/data-exports/' . $id . '/file'), 404));
        self::assertSame([], self::list($this->authGet('/me/data-exports')));
    }

    protected function phone(): string
    {
        return '+33615000393';
    }

    private function authPost(string $path): Response
    {
        return $this->post($path, [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]);
    }

    private function drain(): void
    {
        MessengerDrain::drain($this->browser->getContainer(), ['async_external']);
    }

    private function download(string $url): Response
    {
        $this->browser->request('GET', $url, server: ['REMOTE_ADDR' => $this->clientIp]);

        return $this->browser->getResponse();
    }

    /**
     * @return list<RequestNotification>
     */
    private function notices(): array
    {
        return array_values(array_filter($this->bus()->recorded(RequestNotification::class), static fn (RequestNotification $n): bool => 'PORTAL_DATA_EXPORT_READY' === $n->purpose));
    }

    /**
     * The delays (seconds) of the export deletions waiting on the `async` transport.
     *
     * @return list<int>
     */
    private function scheduledDeletions(): array
    {
        $delays = $this->connection()->fetchFirstColumn(
            "SELECT TIMESTAMPDIFF(SECOND, created_at, available_at) FROM shared__messenger_messages WHERE queue_name = 'default' AND body LIKE :class",
            ['class' => '%DeleteDataExportFile%'],
        );

        return array_map(static fn (mixed $delay): int => is_numeric($delay) ? (int) $delay : -1, $delays);
    }

    private function storage(): FileStorageInterface
    {
        return $this->service(FileStorageInterface::class);
    }

    private function stored(string $key): bool
    {
        try {
            $this->storage()->read($key);

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function commands(): CommandBusInterface
    {
        return $this->service(CommandBusInterface::class);
    }

    /**
     * @return array<string, string> name → content, in archive order
     */
    private static function unzip(string $archive): array
    {
        $path = tempnam(sys_get_temp_dir(), 'kiveto-test-');
        self::assertIsString($path);
        file_put_contents($path, $archive);

        try {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            $files = [];

            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = $zip->getNameIndex($i);
                self::assertIsString($name);
                $files[$name] = (string) $zip->getFromIndex($i);
            }
            $zip->close();

            return $files;
        } finally {
            unlink($path);
        }
    }
}
