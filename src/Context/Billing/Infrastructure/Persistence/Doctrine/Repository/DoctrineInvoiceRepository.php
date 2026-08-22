<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Domain\Exception\InvoiceNotFoundException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\InvoiceLineEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\InvoiceMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineInvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvoiceMapper $mapper,
    ) {
    }

    public function save(Invoice $invoice): void
    {
        $invoiceUuid = Uuid::fromString($invoice->id()->toString());
        $entity      = $this->entityManager->getRepository(InvoiceEntity::class)->find($invoiceUuid);

        if (null === $entity) {
            $entity = $this->mapper->toEntity($invoice);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->applyMutableState($invoice, $entity);
        }

        $this->replaceLines($invoice, $invoiceUuid);
        $this->appendPayments($invoice, $invoiceUuid);

        $this->entityManager->flush();
    }

    public function get(ClinicId $clinicId, InvoiceId $invoiceId): Invoice
    {
        $invoice = $this->findById($clinicId, $invoiceId);

        if (null === $invoice) {
            throw new InvoiceNotFoundException($invoiceId->toString());
        }

        return $invoice;
    }

    public function findById(ClinicId $clinicId, InvoiceId $invoiceId): ?Invoice
    {
        $entity = $this->entityManager->getRepository(InvoiceEntity::class)->findOneBy([
            'id'       => Uuid::fromString($invoiceId->toString()),
            'clinicId' => Uuid::fromString($clinicId->toString()),
        ]);

        return null === $entity ? null : $this->hydrate($entity);
    }

    public function findForAdmission(ClinicId $clinicId, AdmissionId $admissionId): ?Invoice
    {
        /** @var list<InvoiceEntity> $entities */
        $entities = $this->entityManager->getRepository(InvoiceEntity::class)->findBy(
            [
                'clinicId'    => Uuid::fromString($clinicId->toString()),
                'admissionId' => Uuid::fromString($admissionId->toString()),
            ],
            ['createdAt' => 'DESC'],
        );

        foreach ($entities as $entity) {
            if (InvoiceStatus::CANCELLED !== $entity->getStatus()) {
                return $this->hydrate($entity);
            }
        }

        return null;
    }

    private function hydrate(InvoiceEntity $entity): Invoice
    {
        /** @var list<InvoiceLineEntity> $lines */
        $lines = $this->entityManager->getRepository(InvoiceLineEntity::class)->findBy(
            ['invoiceId' => $entity->getId()],
            ['position' => 'ASC'],
        );

        /** @var list<PaymentEntity> $payments */
        $payments = $this->entityManager->getRepository(PaymentEntity::class)->findBy(
            ['invoiceId' => $entity->getId()],
            ['position' => 'ASC'],
        );

        return $this->mapper->toDomain($entity, $lines, $payments);
    }

    /**
     * Lines are only mutable while the invoice is a draft, and a draft is
     * rebuilt wholesale, so replacing them is both correct and cheaper than
     * diffing.
     */
    private function replaceLines(Invoice $invoice, Uuid $invoiceUuid): void
    {
        $repository = $this->entityManager->getRepository(InvoiceLineEntity::class);
        $keptIds    = [];

        foreach ($invoice->lines() as $line) {
            $keptIds[] = $line->id()->toString();
        }

        /** @var list<InvoiceLineEntity> $existing */
        $existing = $repository->findBy(['invoiceId' => $invoiceUuid]);

        foreach ($existing as $entity) {
            if (!\in_array($entity->getId()->toRfc4122(), $keptIds, true)) {
                $this->entityManager->remove($entity);
            }
        }

        $existingIds = array_map(
            static fn (InvoiceLineEntity $entity): string => $entity->getId()->toRfc4122(),
            $existing,
        );

        foreach ($invoice->lines() as $position => $line) {
            if (\in_array($line->id()->toString(), $existingIds, true)) {
                continue;
            }

            $this->entityManager->persist($this->mapper->lineToEntity($line, $invoice->id(), $position));
        }
    }

    private function appendPayments(Invoice $invoice, Uuid $invoiceUuid): void
    {
        $repository = $this->entityManager->getRepository(PaymentEntity::class);

        /** @var list<PaymentEntity> $existing */
        $existing = $repository->findBy(['invoiceId' => $invoiceUuid]);

        $existingIds = array_map(
            static fn (PaymentEntity $entity): string => $entity->getId()->toRfc4122(),
            $existing,
        );

        foreach ($invoice->payments() as $position => $payment) {
            if (\in_array($payment->id()->toString(), $existingIds, true)) {
                continue;
            }

            $this->entityManager->persist($this->mapper->paymentToEntity(
                $payment,
                $invoice->id(),
                $invoice->clinicId(),
                $invoice->currency()->toString(),
                $position,
            ));
        }
    }
}
