<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Animal\Domain\ValueObject\AnimalStatus;
use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Domain\ValueObject\TransferStatus;
use App\Shared\Application\Pdf\PdfRendererInterface;
use App\Tests\Support\Document\RecordingPdfRenderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Slice 3: the vaccination booklet redirect and its signed download
 * (AC 25, 26, 115, 116). The PDF service is replaced by a recorder: the
 * booklet's HTML is checked, the served "PDF" is the recorder's `%PDF`.
 */
final class VaccinationBookletApiTest extends PetsApiTestCase
{
    private RecordingPdfRenderer $pdf;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.84';
        parent::setUp();
        $this->pdf = new RecordingPdfRenderer();
        $this->browser->getContainer()->set(PdfRendererInterface::class, $this->pdf);
    }

    public function testTheBookletRedirectsToAFiveMinuteSignedUrlServingThePdfWithoutToken(): void
    {
        $pet = $this->pet('Rex', attributes: ['microchipNumber' => '250269812345678']);
        $this->careAct($pet, PreventiveCareKind::VACCINATION, 'CHPPiL', '2026-03-01', '2027-03-01');
        $this->careAct($pet, PreventiveCareKind::INTERNAL_PARASITE, 'Milbemax', '2026-08-01', '2026-11-01');

        $redirect = $this->authGet($this->booklet($pet));
        self::assertSame(302, $redirect->getStatusCode());
        self::assertStringContainsString('no-store', (string) $redirect->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $redirect->headers->get('Cache-Control'));
        $url = (string) $redirect->headers->get('Location');
        self::assertMatchesRegularExpression('#/api/portal/v1/files/booklets/' . self::CLINIC . '/' . $pet . '/[0-9a-f]{64}\?#', $url);

        $html = $this->renderedHtml();
        self::assertStringContainsString('Rex', $html);
        self::assertStringContainsString('250269812345678', $html);
        self::assertStringContainsString('Clinique des Lilas', $html);
        self::assertStringContainsString('CHPPiL', $html);
        self::assertStringNotContainsString('Milbemax', $html);

        $pdf = $this->download($url);
        self::assertSame(200, $pdf->getStatusCode());
        self::assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', (string) $pdf->headers->get('Cache-Control'));
        self::assertSame('%PDF', $pdf->getContent());

        self::assertSame('not_found', self::problemType($this->download(str_replace(self::CLINIC, self::OTHER_CLINIC, $url)), 404));
        self::assertSame('not_found', self::problemType($this->download(substr($url, 0, -4) . 'abcd'), 404));
        self::assertSame('not_found', self::problemType($this->download(strtok($url, '?') ?: $url), 404));

        self::mockTime('+6 minutes');
        self::assertSame('not_found', self::problemType($this->download($url), 404));
    }

    public function testUnchangedInputsReuseTheFileAndAChangeRendersANewOneDeletingTheOldLater(): void
    {
        $pet = $this->pet('Rex');
        $this->careAct($pet, PreventiveCareKind::VACCINATION, 'CHPPiL', '2026-03-01', '2027-03-01');

        $first = $this->path($this->authGet($this->booklet($pet)));
        self::assertNotNull($this->pdf->request);
        $this->pdf->request = null;

        self::assertSame($first, $this->path($this->authGet($this->booklet($pet))));
        self::assertNull($this->pdf->request, 'the cached booklet is reused');
        self::assertSame([], $this->scheduledDeletions());

        $this->careAct($pet, PreventiveCareKind::VACCINATION, 'Rage', '2026-10-01', '2027-10-01');
        $second = $this->path($this->authGet($this->booklet($pet)));

        self::assertNotSame($first, $second);
        self::assertStringContainsString('Rage', $this->renderedHtml());
        $deletions = $this->scheduledDeletions();
        self::assertCount(1, $deletions);
        // The previous key, `client_portal/booklets/<animalId>-<hash>.pdf`.
        self::assertStringContainsString($pet . '-' . basename($first) . '.pdf', $deletions[0]['body']);
        self::assertGreaterThanOrEqual(600, $deletions[0]['delay']);
    }

    public function testThePdfServiceDownAnswersARetryableProblem(): void
    {
        $pet             = $this->pet('Rex');
        $this->pdf->fail = true;

        $response = $this->authGet($this->booklet($pet));

        self::assertSame('service_unavailable', self::problemType($response, 503));
        self::assertSame('60', $response->headers->get('Retry-After'));
        self::assertSame([], $this->scheduledDeletions());

        $this->pdf->fail = false;
        self::assertSame(302, $this->authGet($this->booklet($pet))->getStatusCode(), 'nothing cached by the failure');
    }

    public function testAPetWithoutVaccinationGetsABookletSayingSo(): void
    {
        $pet = $this->pet('Rex');

        self::assertSame(302, $this->authGet($this->booklet($pet))->getStatusCode());
        self::assertStringContainsString('Aucune vaccination enregistrée', $this->renderedHtml());
    }

    public function testADeceasedPetKeepsItsBookletWhileInvisiblePetsAnswerNotFoundWithoutRender(): void
    {
        $deceased = $this->pet('Anubis', attributes: ['lifeStatus' => LifeStatus::DECEASED, 'deceasedAt' => new \DateTimeImmutable('2026-06-01')]);
        self::assertSame(302, $this->authGet($this->booklet($deceased))->getStatusCode());
        $this->pdf->request = null;

        $invisible = [
            $this->pet('Voisin', self::OTHER_CLIENT),
            $this->pet('Archivé', attributes: ['status' => AnimalStatus::ARCHIVED]),
            $this->pet('Vendu', attributes: ['transferStatus' => TransferStatus::SOLD, 'soldAt' => new \DateTimeImmutable('2026-09-01')]),
        ];

        foreach ($invisible as $pet) {
            self::assertSame('not_found', self::problemType($this->authGet($this->booklet($pet)), 404));
        }
        self::assertNull($this->pdf->request, 'nothing was rendered');
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::OTHER_CLINIC . '/pets/' . $deceased . '/vaccination-booklet'), 404));
    }

    protected function phone(): string
    {
        return '+33615000304';
    }

    /**
     * The deletions waiting on the `async` transport, with their delay in seconds.
     *
     * @return list<array{body: string, delay: int}>
     */
    private function scheduledDeletions(): array
    {
        $rows = $this->connection()->fetchAllAssociative(
            "SELECT body, TIMESTAMPDIFF(SECOND, created_at, available_at) AS delay FROM shared__messenger_messages WHERE queue_name = 'default' AND body LIKE :class",
            // The serialized envelope escapes the namespace separators: match the short name.
            ['class' => '%DeleteStoredDocumentFile%'],
        );

        return array_map(static fn (array $row): array => ['body' => \is_string($row['body']) ? $row['body'] : '', 'delay' => is_numeric($row['delay']) ? (int) $row['delay'] : 0], $rows);
    }

    private function booklet(string $petId): string
    {
        return '/clinics/' . self::CLINIC . '/pets/' . $petId . '/vaccination-booklet';
    }

    private function download(string $url): Response
    {
        $this->browser->request('GET', $url, server: ['REMOTE_ADDR' => $this->clientIp]);

        return $this->browser->getResponse();
    }

    /** The path of the signed URL, without its signature. */
    private function path(Response $redirect): string
    {
        self::assertSame(302, $redirect->getStatusCode());

        return (string) parse_url((string) $redirect->headers->get('Location'), \PHP_URL_PATH);
    }

    private function renderedHtml(): string
    {
        self::assertNotNull($this->pdf->request, 'a booklet was rendered');

        return $this->pdf->request->html;
    }
}
