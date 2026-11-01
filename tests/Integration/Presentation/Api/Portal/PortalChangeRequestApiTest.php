<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Animal\Domain\ValueObject\AnimalStatus;
use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Presentation\Api\Shared\Idempotency\IdempotentProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 6: pet change requests (AC 67 API part, 233, 235, 236). The clinic
 * applies a change by hand: the animal never changes here.
 */
final class PortalChangeRequestApiTest extends RequestsApiTestCase
{
    private string $rex;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.162';
        parent::setUp();

        $this->rex = $this->pet('Rex');
    }

    public function testAWeightRequestIsNormalisedAndLeavesTheAnimalUnchanged(): void
    {
        $answer = self::json($this->change($this->rex, ['field' => 'weight', 'proposedValue' => '12,4', 'measuredOn' => '2026-11-01'], 'key-weight'), 202);

        $requestId = self::string($answer['requestId']);
        self::assertSame(['requestId'], array_keys($answer));
        $row = $this->connection()->fetchAssociative('SELECT field, proposed_value, normalised_value, status FROM client_portal__pet_change_requests WHERE id = UUID_TO_BIN(?)', [$requestId]);
        self::assertIsArray($row);
        self::assertSame(['weight', '12,4', 'submitted'], [$row['field'], $row['proposed_value'], $row['status']]);
        $normalised = json_decode(self::string($row['normalised_value']), true);
        self::assertIsArray($normalised);
        self::assertSame([12400, '2026-11-01'], [$normalised['weightGrams'] ?? null, $normalised['measuredOn'] ?? null]);
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM animal__weight_records WHERE animal_id = UUID_TO_BIN(?)', [$this->rex]), 'no weighing written');
    }

    public function testANameRequestSnapshotsTheCurrentName(): void
    {
        $requestId = self::string(self::json($this->change($this->rex, ['field' => 'name', 'proposedValue' => ' Rex II '], 'key-name'), 202)['requestId']);

        self::assertSame('Rex', $this->connection()->fetchOne('SELECT current_value_at_submit FROM client_portal__pet_change_requests WHERE id = UUID_TO_BIN(?)', [$requestId]));
        self::assertSame('Rex', $this->connection()->fetchOne('SELECT name FROM animal__animals WHERE id = UUID_TO_BIN(?)', [$this->rex]));
    }

    public function testAReplayAnswersTheSameWithoutASecondRequest(): void
    {
        $body  = ['field' => 'chip_number', 'proposedValue' => '250269801234567'];
        $first = self::json($this->change($this->rex, $body, 'key-chip'), 202);

        $replay = $this->change($this->rex, $body, 'key-chip');

        self::assertSame('true', $replay->headers->get(IdempotentProcessor::REPLAYED_HEADER));
        self::assertSame($first, self::json($replay, 202));
        self::assertSame(1, $this->requests($this->rex));
        self::assertSame('idempotency_key_missing', self::problemType($this->change($this->rex, $body, null), 400));
    }

    public function testADeceasedMissingOrArchivedPetIsNotEligible(): void
    {
        $pets = [
            'deceased' => $this->pet('Anubis', attributes: ['lifeStatus' => LifeStatus::DECEASED, 'deceasedAt' => new \DateTimeImmutable('2026-06-01')]),
            'missing'  => $this->pet('Bandit', attributes: ['lifeStatus' => LifeStatus::MISSING, 'missingSince' => new \DateTimeImmutable('2026-08-01')]),
            'archived' => $this->pet('Archivé', attributes: ['status' => AnimalStatus::ARCHIVED]),
        ];

        foreach ($pets as $case => $petId) {
            self::assertSame('pet_not_eligible', self::problemType($this->change($petId, ['field' => 'name', 'proposedValue' => 'Nouveau'], 'key-' . $case), 422), $case);
        }
    }

    public function testAnotherClientsUnknownOrForeignClinicsPetIsNotFound(): void
    {
        $body = ['field' => 'name', 'proposedValue' => 'Nouveau'];

        self::assertSame('not_found', self::problemType($this->change($this->pet('Nala', self::OTHER_CLIENT), $body, 'key-foreign'), 404));
        self::assertSame('not_found', self::problemType($this->change(Uuid::v7()->toRfc4122(), $body, 'key-unknown'), 404));
        self::assertSame('not_found', self::problemType($this->change($this->rex, $body, 'key-tenant', self::OTHER_CLINIC), 404));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideAFieldRuleIsAViolationOnItsPropertyCases')]
    public function testAFieldRuleIsAViolationOnItsProperty(array $body, string $path): void
    {
        $problem = self::json($this->change($this->rex, $body, 'key-invalid'), 422);

        self::assertSame('validation_failed', $problem['type']);
        self::assertSame($path, self::firstViolationPath($problem));
        self::assertSame(0, $this->requests($this->rex));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideAFieldRuleIsAViolationOnItsPropertyCases(): iterable
    {
        yield 'unknown field' => [['field' => 'color', 'proposedValue' => 'roux'], 'field'];
        yield 'name too long' => [['field' => 'name', 'proposedValue' => str_repeat('a', 61)], 'proposedValue'];
        yield 'blank name' => [['field' => 'name', 'proposedValue' => '  '], 'proposedValue'];
        yield 'weight too low' => [['field' => 'weight', 'proposedValue' => '0,04'], 'proposedValue'];
        yield 'weight too high' => [['field' => 'weight', 'proposedValue' => '150.5'], 'proposedValue'];
        yield 'weight not a number' => [['field' => 'weight', 'proposedValue' => 'douze'], 'proposedValue'];
        yield 'weighed tomorrow' => [['field' => 'weight', 'proposedValue' => '12', 'measuredOn' => '2026-11-03'], 'measuredOn'];
        yield 'malformed weighing day' => [['field' => 'weight', 'proposedValue' => '12', 'measuredOn' => '03/11/2026'], 'measuredOn'];
        yield 'unknown sterilization' => [['field' => 'sterilization', 'proposedValue' => 'castré'], 'proposedValue'];
        yield 'short chip' => [['field' => 'chip_number', 'proposedValue' => '25026980123456'], 'proposedValue'];
        yield 'other without note' => [['field' => 'other', 'proposedValue' => 'couleur'], 'note'];
        yield 'long note' => [['field' => 'other', 'proposedValue' => 'couleur', 'note' => str_repeat('a', 501)], 'note'];
        yield 'long value' => [['field' => 'other', 'proposedValue' => str_repeat('a', 121), 'note' => 'Robe'], 'proposedValue'];
    }

    public function testThreeOpenRequestsOnOnePetAndFieldAreTheMost(): void
    {
        foreach (['Rex I', 'Rex II', 'Rex III'] as $i => $name) {
            self::json($this->change($this->rex, ['field' => 'name', 'proposedValue' => $name], 'key-open-' . $i), 202);
        }

        $limited = $this->change($this->rex, ['field' => 'name', 'proposedValue' => 'Rex IV'], 'key-open-3');
        self::assertSame('rate_limited', self::problemType($limited, 429));
        self::assertNull($limited->headers->get('Retry-After'), 'no date frees an open request');

        self::json($this->change($this->rex, ['field' => 'sterilization', 'proposedValue' => 'neutered'], 'key-other-field'), 202);
    }

    public function testTenRequestsPerDayInOneClinicAreTheMost(): void
    {
        $prescription = $this->prescription($this->rex);

        for ($i = 0; $i < 9; ++$i) {
            self::json($this->change($this->pet('Chat ' . $i), ['field' => 'sterilization', 'proposedValue' => 'neutered'], 'key-day-' . $i), 202);
        }
        self::json($this->authPost(self::clinicPath('/prescriptions/' . $prescription . '/renewal-requests'), ['petCondition' => 'improved'], 'key-day-renewal'), 202);

        $limited = $this->change($this->rex, ['field' => 'name', 'proposedValue' => 'Rex II'], 'key-day-10');
        self::assertSame('rate_limited', self::problemType($limited, 429));
        self::assertGreaterThan(0, (int) $limited->headers->get('Retry-After'));
        self::assertSame(0, $this->requests($this->rex));
    }

    protected function phone(): string
    {
        return '+33615000602';
    }

    /**
     * @param array<string, mixed> $body
     */
    private function change(string $petId, array $body, ?string $key, string $clinicId = self::CLINIC): Response
    {
        return $this->authPost(self::clinicPath('/pets/' . $petId . '/change-requests', $clinicId), $body, $key);
    }

    private function requests(string $petId): int
    {
        return $this->number('SELECT COUNT(*) FROM client_portal__pet_change_requests WHERE pet_id = UUID_TO_BIN(?)', [$petId]);
    }
}
