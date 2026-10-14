<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Domain\CashClosing;
use App\Context\Billing\Domain\Repository\CashClosingRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\CashClosingId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\CashClosingEntity;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineCashClosingRepository implements CashClosingRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(CashClosing $closing): void
    {
        $by     = $closing->closedByUserId();
        $entity = new CashClosingEntity();

        $entity->setId(Uuid::fromString($closing->id()->toString()));
        $entity->setClinicId(Uuid::fromString($closing->clinicId()->toString()));
        $entity->setNumber($closing->number());
        $entity->setBusinessDate($closing->businessDate());
        $entity->setExpected($closing->expectedMinorUnits());
        $entity->setCountedCashMinorUnits($closing->countedCashMinorUnits());
        $entity->setCountedChequesMinorUnits($closing->countedChequesMinorUnits());
        $entity->setComment($closing->comment());
        $entity->setCurrency($closing->currency()->toString());
        $entity->setClosedAt($closing->closedAt());
        $entity->setClosedByUserId(null !== $by ? Uuid::fromString($by) : null);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findForDate(ClinicId $clinicId, string $businessDate): ?CashClosing
    {
        $entity = $this->entityManager->getRepository(CashClosingEntity::class)->findOneBy([
            'clinicId'     => Uuid::fromString($clinicId->toString()),
            'businessDate' => $businessDate,
        ]);

        if (null === $entity) {
            return null;
        }

        $by = $entity->getClosedByUserId();

        return CashClosing::reconstitute(
            id: CashClosingId::fromString($entity->getId()->toRfc4122()),
            clinicId: $clinicId,
            number: $entity->getNumber(),
            businessDate: $entity->getBusinessDate(),
            expectedMinorUnits: $entity->getExpected(),
            countedCashMinorUnits: $entity->getCountedCashMinorUnits(),
            countedChequesMinorUnits: $entity->getCountedChequesMinorUnits(),
            comment: $entity->getComment(),
            currency: CurrencyCode::fromString($entity->getCurrency()),
            closedAt: $entity->getClosedAt(),
            closedByUserId: $by?->toRfc4122(),
        );
    }

    public function nextNumber(ClinicId $clinicId): int
    {
        // Read under lock: two desks closing at the same second must not share a number.
        $max = $this->entityManager->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(number), 0) FROM billing__cash_closings WHERE clinic_id = :clinicId FOR UPDATE',
            ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()],
        );

        return (is_numeric($max) ? (int) $max : 0) + 1;
    }
}
