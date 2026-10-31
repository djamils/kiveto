<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Fixtures\Context\Consultation\Factory\ConsultationEntityFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 3: the timeline of a pet = its visits whose report was shared with
 * the caller (AC 23, 112, 114).
 */
final class PetTimelineApiTest extends PetsApiTestCase
{
    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.82';
        parent::setUp();
    }

    public function testOnlyVisitsWithASharedReportAreListedLatestCloseFirst(): void
    {
        $pet = $this->pet('Rex');

        $checkUp = $this->visit('2026-09-01 08:00:00', null, ['Vaccination', 'Bilan annuel']);
        $report  = $this->report(self::CLIENT, '2026-09-01 09:00:00', [$pet], $checkUp, 'Dr Claire Lambert');
        $rx      = $this->document(DocumentType::PRESCRIPTION, self::CLIENT, '2026-09-01 09:05:00', [$pet], $checkUp);
        $this->document(DocumentType::INVOICE, self::OTHER_CLIENT, '2026-09-01 09:10:00', [$pet], $checkUp);

        // Shared by a vet who has left the clinic since: the snapshot stays.
        $cough       = $this->visit('2026-10-20 08:30:00', 'Toux.', []);
        $coughReport = $this->report(self::CLIENT, '2026-10-20 09:30:00', [$pet], $cough, 'Dr Parti Depuis');

        $plain       = $this->visit('2026-10-25 13:00:00', null, []);
        $plainReport = $this->report(self::CLIENT, '2026-10-25 14:00:00', [$pet], $plain, null);

        $this->visit('2026-10-28 08:00:00', 'Boiterie', []);

        $page = self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet . '/timeline'), 200);
        self::assertNull($page['nextCursor']);
        self::assertSame([
            ['visitId' => $plain, 'date' => '2026-10-25T14:00:00+01:00', 'vet' => null, 'title' => 'Consultation', 'documents' => [['id' => $plainReport, 'type' => 'report']]],
            ['visitId' => $cough, 'date' => '2026-10-20T10:30:00+02:00', 'vet' => 'Dr Parti Depuis', 'title' => 'Toux', 'documents' => [['id' => $coughReport, 'type' => 'report']]],
            ['visitId' => $checkUp, 'date' => '2026-09-01T10:00:00+02:00', 'vet' => 'Dr Claire Lambert', 'title' => 'Vaccination et bilan annuel', 'documents' => [['id' => $rx, 'type' => 'prescription'], ['id' => $report, 'type' => 'report']]],
        ], self::entries($page));
    }

    public function testMoreThanTwentyVisitsArePagedWithoutDuplicatesOrGaps(): void
    {
        $pet      = $this->pet('Rex');
        $expected = [];
        for ($day = 1; $day <= 22; ++$day) {
            $visit      = Uuid::v7()->toRfc4122();
            $expected[] = $visit;
            $this->report(self::CLIENT, \sprintf('2026-09-%02d 10:00:00', $day), [$pet], $visit);
        }
        $expected = array_reverse($expected);

        $first = self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet . '/timeline'), 200);
        self::assertCount(20, self::entries($first));
        $second = self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet . '/timeline?cursor=' . self::string($first['nextCursor'])), 200);
        self::assertNull($second['nextCursor']);
        self::assertSame($expected, array_column([...self::entries($first), ...self::entries($second)], 'visitId'));

        // Without a consultation answer, the registry's own date and title stand in.
        self::assertSame('2026-09-22T12:00:00+02:00', self::entries($first)[0]['date']);
        self::assertSame('Compte rendu du 22/09/2026', self::entries($first)[0]['title']);

        $clamped = self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet . '/timeline?limit=500'), 200);
        self::assertCount(22, self::entries($clamped));
        self::assertNull($clamped['nextCursor']);
        self::assertCount(5, self::entries(self::json($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet . '/timeline?limit=5'), 200)));
    }

    public function testAMalformedOrWrongShapeCursorIsRefused(): void
    {
        $pet = $this->pet('Rex');

        self::assertSame('cursor_invalid', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet . '/timeline?cursor=%%%'), 400));
        $wrongShape = rtrim(strtr(base64_encode('{"v":1,"p":{"at":"yesterday","id":"x"}}'), '+/', '-_'), '=');
        self::assertSame('cursor_invalid', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/pets/' . $pet . '/timeline?cursor=' . $wrongShape), 400));
    }

    protected function phone(): string
    {
        return '+33615000302';
    }

    /**
     * A consultation of the pet, closed one hour after its start.
     *
     * @param list<string> $motifs
     */
    private function visit(string $startedAtUtc, ?string $chiefComplaint, array $motifs): string
    {
        $id        = Uuid::v7()->toRfc4122();
        $startedAt = new \DateTimeImmutable($startedAtUtc, new \DateTimeZone('UTC'));

        ConsultationEntityFactory::new()->withId($id)->withClinicId(self::CLINIC)->withStatus(ConsultationStatus::CLOSED)->create([
            'chiefComplaint' => $chiefComplaint,
            'startedAtUtc'   => $startedAt,
            'closedAtUtc'    => $startedAt->modify('+1 hour'),
        ]);

        foreach ($motifs as $position => $label) {
            $this->connection()->insert('consultation__motifs', [
                'id'              => Uuid::v7()->toBinary(),
                'consultation_id' => Uuid::fromString($id)->toBinary(),
                'label'           => $label,
                'position'        => $position,
            ]);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return list<array<string, mixed>>
     */
    private static function entries(array $page): array
    {
        return self::rows($page['items'] ?? null);
    }
}
