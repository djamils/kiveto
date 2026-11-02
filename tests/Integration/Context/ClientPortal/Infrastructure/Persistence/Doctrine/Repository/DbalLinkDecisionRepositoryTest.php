<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Link\LinkDecision;
use App\Context\ClientPortal\Domain\Link\LinkDecisionId;
use App\Context\ClientPortal\Domain\Link\LinkDecisionOutcome;
use App\Context\ClientPortal\Domain\Link\LinkDecisionRepositoryInterface;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\LinkDecisionEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalLinkDecisionRepositoryTest extends KernelTestCase
{
    private const string D1      = '01960000-0000-7000-8000-0000000ed001';
    private const string D2      = '01960000-0000-7000-8000-0000000ed002';
    private const string D3      = '01960000-0000-7000-8000-0000000ed003';
    private const string USER    = '01960000-0000-7000-8000-0000000ed101';
    private const string CLINIC  = '01960000-0000-7000-8000-0000000ed201';
    private const string CLINIC2 = '01960000-0000-7000-8000-0000000ed202';
    private const string CLIENT  = '01960000-0000-7000-8000-0000000ed301';
    private const string CLIENT2 = '01960000-0000-7000-8000-0000000ed302';
    private const string STAFF   = '01960000-0000-7000-8000-0000000ed401';

    public function testADecisionIsReadBackWholeAndListedLatestFirst(): void
    {
        $repository = $this->repository();
        $repository->save($this->decision(self::D1, self::CLINIC, LinkDecisionOutcome::CONFIRMED, [self::CLIENT, self::CLIENT2], '2026-11-01 09:00'));
        $repository->save($this->decision(self::D2, self::CLINIC, LinkDecisionOutcome::REJECTED, [self::CLIENT], '2026-11-02 09:00'));
        $repository->save($this->decision(self::D3, self::CLINIC2, LinkDecisionOutcome::REJECTED, [self::CLIENT], '2026-11-03 09:00'));

        $found = $repository->findInClinic(self::CLINIC, self::D1);
        self::assertNotNull($found);
        self::assertSame(
            [self::D1, self::CLINIC, self::USER, LinkDecisionOutcome::CONFIRMED, [self::CLIENT, self::CLIENT2], '2026-11-01 09:00 UTC', self::STAFF, 'Dr Lemoine'],
            [$found->id()->toString(), $found->clinicId(), $found->userId(), $found->outcome(), $found->clientIds(), $found->decidedAt()->format('Y-m-d H:i e'), $found->decidedByUserId(), $found->decidedByName()],
        );
        self::assertNull($repository->findInClinic(self::CLINIC2, self::D1));
        self::assertNull($repository->findInClinic(self::CLINIC, 'nope'));

        self::assertSame([self::D2, self::D1], array_map(static fn (LinkDecision $d): string => $d->id()->toString(), $repository->findByClinic(self::CLINIC, 10)));
        self::assertCount(1, $repository->findByClinic(self::CLINIC, 1));

        // The Doctrine entity only carries the schema
        $entity = new LinkDecisionEntity(Uuid::v7(), Uuid::v7(), Uuid::v7(), 'confirmed', [self::CLIENT], new \DateTimeImmutable(), Uuid::v7(), 'Dr Lemoine');
        self::assertSame(['confirmed', [self::CLIENT]], [$entity->outcome, $entity->clientIds]);
    }

    /**
     * @param list<string> $clientIds
     */
    private function decision(string $id, string $clinicId, LinkDecisionOutcome $outcome, array $clientIds, string $at): LinkDecision
    {
        return LinkDecision::record(LinkDecisionId::fromString($id), $clinicId, self::USER, $outcome, $clientIds, new \DateTimeImmutable($at, new \DateTimeZone('UTC')), self::STAFF, 'Dr Lemoine');
    }

    private function repository(): LinkDecisionRepositoryInterface
    {
        $repository = self::getContainer()->get(LinkDecisionRepositoryInterface::class);
        \assert($repository instanceof LinkDecisionRepositoryInterface);

        return $repository;
    }
}
