<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain\Service;

use App\System\PharmaceuticalRegistry\Domain\Entity\SnapshotEntry;
use App\System\PharmaceuticalRegistry\Domain\Repository\MarketingAuthorizationHashProjection;
use App\System\PharmaceuticalRegistry\Domain\Service\RegistryDiffCalculator;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\DiffKind;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use PHPUnit\Framework\TestCase;

final class RegistryDiffCalculatorTest extends TestCase
{
    private RegistryDiffCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new RegistryDiffCalculator();
    }

    public function testEmptyProducesEmptyDiff(): void
    {
        $diff = $this->calculator->calculate([], []);

        self::assertEmpty($diff);
    }

    public function testNewProductProducesCreate(): void
    {
        $entry = new SnapshotEntry('FR/001', $this->hash('data'), []);

        $diff = $this->calculator->calculate([$entry], []);

        self::assertCount(1, $diff);
        self::assertSame(DiffKind::CREATE, $diff[0]->diffKind());
        self::assertNull($diff[0]->targetUuid());
    }

    public function testSameHashProducesSkip(): void
    {
        $hash       = $this->hash('data');
        $entry      = new SnapshotEntry('FR/001', $hash, []);
        $projection = new MarketingAuthorizationHashProjection('FR/001', $hash, $this->id('1'));

        $diff = $this->calculator->calculate([$entry], [$projection]);

        self::assertEmpty($diff);
    }

    public function testDifferentHashProducesUpdate(): void
    {
        $entry      = new SnapshotEntry('FR/001', $this->hash('new'), []);
        $projection = new MarketingAuthorizationHashProjection('FR/001', $this->hash('old'), $this->id('1'));

        $diff = $this->calculator->calculate([$entry], [$projection]);

        self::assertCount(1, $diff);
        self::assertSame(DiffKind::UPDATE, $diff[0]->diffKind());
    }

    public function testMissingFromSnapshotProducesWithdraw(): void
    {
        $projection = new MarketingAuthorizationHashProjection('FR/001', $this->hash('data'), $this->id('1'));

        $diff = $this->calculator->calculate([], [$projection]);

        self::assertCount(1, $diff);
        self::assertSame(DiffKind::WITHDRAW, $diff[0]->diffKind());
        self::assertSame($this->id('1')->toString(), $diff[0]->targetUuid()?->toString());
    }

    public function testMixedScenario(): void
    {
        $hash    = $this->hash('data');
        $entries = [
            new SnapshotEntry('FR/001', $this->hash('new_data'), []),
            new SnapshotEntry('FR/002', $hash, []),
            new SnapshotEntry('FR/003', $this->hash('create_me'), []),
        ];

        $projections = [
            new MarketingAuthorizationHashProjection('FR/001', $this->hash('old_data'), $this->id('1')),
            new MarketingAuthorizationHashProjection('FR/002', $hash, $this->id('2')),
            new MarketingAuthorizationHashProjection('FR/004', $this->hash('withdraw_me'), $this->id('4')),
        ];

        $diff = $this->calculator->calculate($entries, $projections);

        $kinds         = array_column($diff, null);
        $createCount   = 0;
        $updateCount   = 0;
        $withdrawCount = 0;

        foreach ($diff as $entry) {
            match ($entry->diffKind()) {
                DiffKind::CREATE   => ++$createCount,
                DiffKind::UPDATE   => ++$updateCount,
                DiffKind::WITHDRAW => ++$withdrawCount,
            };
        }

        self::assertSame(1, $createCount);
        self::assertSame(1, $updateCount);
        self::assertSame(1, $withdrawCount);
    }

    private function hash(string $seed): ContentHash
    {
        return ContentHash::fromString(hash('sha256', $seed));
    }

    private function id(string $seed): MarketingAuthorizationId
    {
        return MarketingAuthorizationId::fromString('018f1b1e-0000-7000-8000-' . str_pad(substr(md5($seed), 0, 12), 12, '0', \STR_PAD_LEFT));
    }
}
