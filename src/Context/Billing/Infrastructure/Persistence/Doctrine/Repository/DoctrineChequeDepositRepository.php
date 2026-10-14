<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Domain\ChequeDeposit;
use App\Context\Billing\Domain\Repository\ChequeDepositRepositoryInterface;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\ChequeDepositEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\ChequeDepositPaymentEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineChequeDepositRepository implements ChequeDepositRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(ChequeDeposit $deposit): void
    {
        $id     = Uuid::fromString($deposit->id()->toString());
        $by     = $deposit->depositedByUserId();
        $entity = new ChequeDepositEntity();

        $entity->setId($id);
        $entity->setClinicId(Uuid::fromString($deposit->clinicId()->toString()));
        $entity->setTotalMinorUnits($deposit->total()->minorUnits());
        $entity->setCurrency($deposit->total()->currency()->toString());
        $entity->setDepositedAt($deposit->depositedAt());
        $entity->setDepositedByUserId(null !== $by ? Uuid::fromString($by) : null);
        $this->entityManager->persist($entity);

        foreach ($deposit->paymentIds() as $paymentId) {
            $cheque = new ChequeDepositPaymentEntity();
            $cheque->setPaymentId(Uuid::fromString($paymentId->toString()));
            $cheque->setDepositId($id);
            $this->entityManager->persist($cheque);
        }

        $this->entityManager->flush();
    }
}
