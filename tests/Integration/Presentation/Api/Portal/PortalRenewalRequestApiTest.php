<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Context\ClientPortal\Application\Command\ResolvePortalRequest\ResolvePortalRequest;
use App\Presentation\Api\Shared\Idempotency\IdempotentProcessor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 6: renewal requests of a prescription (AC 68, 233, 237; AC 243 is
 * proved on two connections by RenewalRequestRaceTest).
 */
final class PortalRenewalRequestApiTest extends RequestsApiTestCase
{
    private string $rex;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.161';
        parent::setUp();

        $this->rex = $this->pet('Rex');
    }

    public function testAnExpiredPrescriptionIsRenewableAndTheDocumentsListShowsItsRequest(): void
    {
        $prescription = $this->prescription($this->rex);

        $answer = self::json($this->renew($prescription, ['petCondition' => 'improved', 'note' => 'Il va mieux.'], 'key-renew'), 202);

        $requestId = self::string($answer['requestId']);
        self::assertSame('submitted', $answer['status']);
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM client_portal__prescription_renewal_requests WHERE prescription_id = UUID_TO_BIN(?)', [$prescription]));

        $documents = self::items(self::json($this->authGet(self::clinicPath('/documents')), 200));
        self::assertCount(1, $documents);
        $summary = $documents[0]['prescription'] ?? null;
        self::assertIsArray($summary);
        self::assertSame(['status' => 'submitted', 'requestId' => $requestId, 'resultingPrescriptionId' => null], $summary['renewalStatus']);
    }

    public function testAReplayAnswersTheSameAndASecondRequestWhileOneIsOpenAnswersIt(): void
    {
        $prescription = $this->prescription($this->rex);
        $first        = self::json($this->renew($prescription, ['petCondition' => 'improved'], 'key-first'), 202);

        $replay = $this->renew($prescription, ['petCondition' => 'improved'], 'key-first');
        self::assertSame('true', $replay->headers->get(IdempotentProcessor::REPLAYED_HEADER));
        $replayed = self::json($replay, 202);
        self::assertSame([$first['requestId'], 'submitted'], [$replayed['requestId'] ?? null, $replayed['status'] ?? null], 'the same body, its keys as stored');

        $second = self::json($this->renew($prescription, ['petCondition' => 'adverse_effects'], 'key-second'), 202);
        self::assertSame($first, $second, 'no 409: the open request answers');
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM client_portal__prescription_renewal_requests WHERE prescription_id = UUID_TO_BIN(?)', [$prescription]));
    }

    public function testTheRouteRequiresAnIdempotencyKey(): void
    {
        self::assertSame('idempotency_key_missing', self::problemType($this->renew($this->prescription($this->rex), ['petCondition' => 'improved'], null), 400));
    }

    public function testAControlledCancelledOrDeadPetsPrescriptionIsNotRenewable(): void
    {
        $ghost    = $this->pet('Anubis', attributes: ['lifeStatus' => LifeStatus::DECEASED, 'deceasedAt' => new \DateTimeImmutable('2026-06-01')]);
        $refusals = [
            'controlled' => $this->prescription($this->rex, controlled: true, issuedAtUtc: '2026-10-20 09:00:00'),
            'cancelled'  => $this->prescription($this->rex, cancelled: true),
            'dead pet'   => $this->prescription($ghost),
        ];

        foreach ($refusals as $case => $prescription) {
            self::assertSame('prescription_not_renewable', self::problemType($this->renew($prescription, ['petCondition' => 'improved'], 'key-' . str_replace(' ', '-', $case)), 422), $case);
        }
    }

    public function testAnotherClientsOrAnUnknownPrescriptionIsNotFound(): void
    {
        $foreign = $this->prescription($this->pet('Nala', self::OTHER_CLIENT), self::OTHER_CLIENT);

        self::assertSame('not_found', self::problemType($this->renew($foreign, ['petCondition' => 'improved'], 'key-foreign'), 404));
        self::assertSame('not_found', self::problemType($this->renew(Uuid::v7()->toRfc4122(), ['petCondition' => 'improved'], 'key-unknown'), 404));
        self::assertSame('not_found', self::problemType($this->renew(Uuid::v7()->toRfc4122(), ['petCondition' => 'improved'], 'key-tenant', self::OTHER_CLINIC), 404));
    }

    public function testARefusalBlocksANewRequestForSevenDays(): void
    {
        $vet          = $this->veterinarian('Claire', 'Rousseau');
        $prescription = $this->prescription($this->rex, prescriberId: $vet);
        $requestId    = self::string(self::json($this->renew($prescription, ['petCondition' => 'no_improvement'], 'key-before'), 202)['requestId']);

        $this->bus()->dispatch(new ResolvePortalRequest(self::CLINIC, $requestId, 'rejected', $vet, 'Dr Claire Rousseau', 'Une consultation est nécessaire.'));

        self::assertSame('prescription_not_renewable', self::problemType($this->renew($prescription, ['petCondition' => 'no_improvement'], 'key-blocked'), 422));

        $this->travelTo('2026-11-09 10:00:01');
        $again = self::json($this->renew($prescription, ['petCondition' => 'no_improvement'], 'key-after'), 202);
        self::assertNotSame($requestId, $again['requestId']);
    }

    /**
     * @param array<string, mixed> $body
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideAnInvalidBodyIsRefusedCases')]
    public function testAnInvalidBodyIsRefused(array $body, string $path): void
    {
        $problem = self::json($this->renew($this->prescription($this->rex), $body, 'key-invalid'), 422);

        self::assertSame('validation_failed', $problem['type']);
        self::assertSame($path, self::firstViolationPath($problem));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideAnInvalidBodyIsRefusedCases(): iterable
    {
        yield 'unknown condition' => [['petCondition' => 'worse'], 'petCondition'];
        yield 'missing condition' => [[], 'petCondition'];
        yield 'long note' => [['petCondition' => 'improved', 'note' => str_repeat('a', 501)], 'note'];
    }

    protected function phone(): string
    {
        return '+33615000601';
    }

    /**
     * @param array<string, mixed> $body
     */
    private function renew(string $prescriptionId, array $body, ?string $key, string $clinicId = self::CLINIC): Response
    {
        return $this->authPost(self::clinicPath('/prescriptions/' . $prescriptionId . '/renewal-requests', $clinicId), $body, $key);
    }
}
