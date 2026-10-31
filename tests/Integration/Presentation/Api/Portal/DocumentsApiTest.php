<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Shared\Domain\Storage\FileStorageInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 2 routes: the documents list, the file redirect and its signed
 * download, the prescription detail and the unread badge of `GET /me`.
 * Reports are used for the registry rows: they carry no live source state.
 */
final class DocumentsApiTest extends PortalApiTestCase
{
    use ClockSensitiveTrait;

    private const string CLINIC       = '01930000-0000-7000-8000-00000000c201';
    private const string OTHER_CLINIC = '01930000-0000-7000-8000-00000000c202';
    private const string CLIENT       = '01930000-0000-7000-8000-00000000d201';
    private const string OTHER_CLIENT = '01930000-0000-7000-8000-00000000d202';
    private const string PET          = '01930000-0000-7000-8000-00000000e201';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientIp = '203.0.113.71';

        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique des Lilas')->create(['timeZone' => 'Europe/Paris']);
        ClinicEntityFactory::new()->withId(self::OTHER_CLINIC)->withName('Clinique du Port')->create();
        PortalUserFactory::new()->withPhone('+33615000101')->create();

        $this->token = self::string($this->signIn('+33615000101')['accessToken']);
        $account     = self::json($this->authGet('/me'), 200)['account'] ?? null;
        self::assertIsArray($account);
        $userId = self::string($account['id'] ?? null);

        $links = $this->service(ClientLinkRepositoryInterface::class);
        $links->save(ClientLink::confirm(ClientLinkId::fromString(Uuid::v7()->toRfc4122()), $userId, self::CLINIC, self::CLIENT, new \DateTimeImmutable('2026-10-01')));
    }

    public function testWithoutAConfirmedLinkEveryClinicRouteAnswersNotFound(): void
    {
        $document = $this->report(self::OTHER_CLINIC, self::OTHER_CLIENT, '2026-10-02 10:00:00');

        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::OTHER_CLINIC . '/documents'), 404));
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::OTHER_CLINIC . '/documents/' . $document . '/file'), 404));
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::OTHER_CLINIC . '/prescriptions/' . Uuid::v7()->toRfc4122()), 404));
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/not-a-uuid/documents'), 404));
    }

    public function testAnotherClientsDocumentOfTheSameClinicIsInvisible(): void
    {
        $foreign = $this->report(self::CLINIC, self::OTHER_CLIENT, '2026-10-02 10:00:00');

        $page = self::json($this->authGet('/clinics/' . self::CLINIC . '/documents'), 200);
        self::assertSame([], $page['items']);
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/documents/' . $foreign . '/file'), 404));
    }

    public function testTheListIsNewestFirstPaginatedAndFilteredByPet(): void
    {
        $old    = $this->report(self::CLINIC, self::CLIENT, '2026-10-02 10:00:00', [self::PET]);
        $middle = $this->report(self::CLINIC, self::CLIENT, '2026-10-03 10:00:00', []);
        $recent = $this->report(self::CLINIC, self::CLIENT, '2026-10-04 10:00:00', [self::PET]);

        $first = self::json($this->authGet('/clinics/' . self::CLINIC . '/documents?limit=2'), 200);
        self::assertSame([$recent, $middle], array_column(self::items($first), 'id'));
        self::assertSame('report', self::items($first)[0]['type']);
        self::assertTrue(self::items($first)[0]['unread']);
        self::assertSame('2026-10-04T12:00:00+02:00', self::items($first)[0]['issuedAt']);
        self::assertNull(self::items($first)[0]['invoice']);

        $second = self::json($this->authGet('/clinics/' . self::CLINIC . '/documents?limit=2&cursor=' . self::string($first['nextCursor'])), 200);
        self::assertSame([$old], array_column(self::items($second), 'id'));
        self::assertNull($second['nextCursor']);

        $pet = self::json($this->authGet('/clinics/' . self::CLINIC . '/documents?petId=' . self::PET), 200);
        self::assertSame([$recent, $old], array_column(self::items($pet), 'id'));
    }

    public function testABadCursorOrTypeIsRefused(): void
    {
        self::assertSame('cursor_invalid', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/documents?cursor=%%%'), 400));
        $wrongShape = rtrim(strtr(base64_encode('{"v":1,"p":{"at":"yesterday","id":"x"}}'), '+/', '-_'), '=');
        self::assertSame('cursor_invalid', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/documents?cursor=' . $wrongShape), 400));
        self::assertSame('validation_failed', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/documents?type=letter'), 422));
    }

    public function testTheFileRouteMarksReadAndRedirectsToAFiveMinuteSignedUrl(): void
    {
        $document = $this->report(self::CLINIC, self::CLIENT, '2026-10-02 10:00:00');
        self::assertSame(1, $this->unread());

        $redirect = $this->authGet('/clinics/' . self::CLINIC . '/documents/' . $document . '/file');
        self::assertSame(302, $redirect->getStatusCode());
        self::assertStringContainsString('no-store', (string) $redirect->headers->get('Cache-Control'));
        self::assertSame(0, $this->unread());

        $url = (string) $redirect->headers->get('Location');
        self::assertStringContainsString('/files/documents/' . self::CLINIC . '/' . $document . '/1', $url);

        $this->browser->request('GET', $url, server: ['REMOTE_ADDR' => $this->clientIp]);
        $pdf = $this->browser->getResponse();
        self::assertSame(200, $pdf->getStatusCode());
        self::assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', (string) $pdf->headers->get('Cache-Control'));
        self::assertSame('%PDF-1.4 report', $pdf->getContent());

        $tampered = str_replace(self::CLINIC, self::OTHER_CLINIC, $url);
        $this->browser->request('GET', $tampered, server: ['REMOTE_ADDR' => $this->clientIp]);
        self::assertSame(404, $this->browser->getResponse()->getStatusCode());

        self::mockTime('+6 minutes');
        $this->browser->request('GET', $url, server: ['REMOTE_ADDR' => $this->clientIp]);
        self::assertSame(404, $this->browser->getResponse()->getStatusCode());
    }

    public function testAWithdrawnDocumentIsNeitherListedNorServed(): void
    {
        $document = $this->report(self::CLINIC, self::CLIENT, '2026-10-02 10:00:00');
        $url      = (string) $this->authGet('/clinics/' . self::CLINIC . '/documents/' . $document . '/file')->headers->get('Location');

        $documents = $this->service(PublishedDocumentRepositoryInterface::class);
        $row       = $documents->findByIdForUpdate($document);
        \assert(null !== $row);
        $row->withdraw(new \DateTimeImmutable());
        $documents->save($row);

        self::assertSame([], self::json($this->authGet('/clinics/' . self::CLINIC . '/documents'), 200)['items']);
        $this->browser->request('GET', $url, server: ['REMOTE_ADDR' => $this->clientIp]);
        self::assertSame(404, $this->browser->getResponse()->getStatusCode());
    }

    private function unread(): int
    {
        $clinics = self::json($this->authGet('/me'), 200)['clinics'] ?? null;
        self::assertIsArray($clinics);
        $clinic = $clinics[0] ?? null;
        self::assertIsArray($clinic);
        self::assertIsInt($clinic['unreadDocuments']);

        return $clinic['unreadDocuments'];
    }

    /**
     * @param list<string> $petIds
     */
    private function report(string $clinicId, string $clientId, string $issuedAt, array $petIds = []): string
    {
        $id  = Uuid::v7()->toRfc4122();
        $key = 'client_portal/documents/' . $id . '-v1.pdf';
        $at  = new \DateTimeImmutable($issuedAt, new \DateTimeZone('UTC'));

        $this->service(FileStorageInterface::class)->storeAt($key, '%PDF-1.4 report');
        $this->service(PublishedDocumentRepositoryInterface::class)->save(PublishedDocument::reconstitute(
            id: PublishedDocumentId::fromString($id),
            clinicId: $clinicId,
            clientId: $clientId,
            type: DocumentType::REPORT,
            sourceRef: Uuid::v7()->toRfc4122(),
            consultationId: null,
            patientId: null,
            petIds: $petIds,
            title: 'Compte rendu du ' . $at->format('d/m/Y'),
            issuedAt: $at,
            practitionerName: 'Dr Claire Lambert',
            storageKey: $key,
            fileVersion: 1,
            pendingFileVersion: null,
            renderAttempts: 0,
            status: PublicationStatus::AVAILABLE,
            publishedAt: $at,
            updatedAt: $at,
        ));

        return $id;
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return list<array<string, mixed>>
     */
    private static function items(array $page): array
    {
        $items = $page['items'] ?? null;
        self::assertIsArray($items);

        $list = [];
        foreach ($items as $item) {
            self::assertIsArray($item);
            $fields = [];
            foreach ($item as $key => $value) {
                $fields[(string) $key] = $value;
            }
            $list[] = $fields;
        }

        return $list;
    }

    private function authGet(string $path): \Symfony\Component\HttpFoundation\Response
    {
        return $this->get($path, ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = $this->browser->getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }
}
