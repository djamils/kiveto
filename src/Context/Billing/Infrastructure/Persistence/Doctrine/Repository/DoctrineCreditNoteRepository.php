<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Exception\CreditNoteNotFoundException;
use App\Context\Billing\Domain\Repository\CreditNoteRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\CreditNoteMapper;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\CreditNoteEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * A credit note is written once, at issue: saving an existing one is a no-op.
 */
final readonly class DoctrineCreditNoteRepository implements CreditNoteRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CreditNoteMapper $mapper,
    ) {
    }

    public function save(CreditNote $creditNote): void
    {
        $existing = $this->entityManager->getRepository(CreditNoteEntity::class)->find(Uuid::fromString($creditNote->id()->toString()));

        if (null === $existing) {
            $this->entityManager->persist($this->mapper->toEntity($creditNote));
        }

        $this->entityManager->flush();
    }

    public function get(ClinicId $clinicId, CreditNoteId $creditNoteId): CreditNote
    {
        $entity = $this->entityManager->getRepository(CreditNoteEntity::class)->findOneBy([
            'id'       => Uuid::fromString($creditNoteId->toString()),
            'clinicId' => Uuid::fromString($clinicId->toString()),
        ]);

        if (null === $entity) {
            throw new CreditNoteNotFoundException($creditNoteId->toString());
        }

        return $this->mapper->toDomain($entity);
    }

    public function forInvoice(ClinicId $clinicId, InvoiceId $invoiceId): array
    {
        /** @var list<CreditNoteEntity> $entities */
        $entities = $this->entityManager->getRepository(CreditNoteEntity::class)->findBy(
            [
                'clinicId'  => Uuid::fromString($clinicId->toString()),
                'invoiceId' => Uuid::fromString($invoiceId->toString()),
            ],
            ['issuedAt' => 'ASC', 'number' => 'ASC'],
        );

        return array_map(fn (CreditNoteEntity $entity): CreditNote => $this->mapper->toDomain($entity), $entities);
    }
}
