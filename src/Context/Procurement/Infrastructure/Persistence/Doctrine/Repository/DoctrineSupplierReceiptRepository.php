<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierReceipt\Repository\SupplierReceiptRepositoryInterface;
use App\Context\Procurement\Domain\SupplierReceipt\SupplierReceipt;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\DeliveryNoteReference;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierReceiptEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\SupplierReceiptMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSupplierReceiptRepository implements SupplierReceiptRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private SupplierReceiptMapper $mapper,
    ) {
    }

    public function save(SupplierReceipt $receipt): void
    {
        $entity  = $this->mapper->toEntity($receipt);
        $managed = $this->em->find(SupplierReceiptEntity::class, $entity->getId());

        if (null !== $managed) {
            // Update the managed entity in-place to avoid EntityIdentityCollisionException.
            // This occurs when findById() loads an entity into the EM identity map and then
            // save() tries to persist a new entity object with the same primary key.
            // Lines are synced by ID: existing managed lines are updated, new lines are added.
            $managed->setStatus($entity->getStatus());
            $managed->setValidatedAt($entity->getValidatedAt());
            $managed->setComment($entity->getComment());
            $managed->setReceivedBy($entity->getReceivedBy());
            $managed->setUpdatedAt($entity->getUpdatedAt());

            // Sync lines: update existing managed line entities, add new ones
            $managedLinesById = [];
            foreach ($managed->getLines() as $managedLine) {
                $managedLinesById[$managedLine->getId()->toRfc4122()] = $managedLine;
            }
            foreach ($entity->getLines() as $newLine) {
                $lineId = $newLine->getId()->toRfc4122();
                if (isset($managedLinesById[$lineId])) {
                    // Update the existing managed line entity in-place
                    $existing = $managedLinesById[$lineId];
                    $existing->setReceivedAmount($newLine->getReceivedAmount());
                    $existing->setReceivedUnit($newLine->getReceivedUnit());
                    $existing->setLotNumber($newLine->getLotNumber());
                    $existing->setLotExpiryDate($newLine->getLotExpiryDate());
                    $existing->setLotManufacturedAt($newLine->getLotManufacturedAt());
                    $existing->setActualUnitPriceMinor($newLine->getActualUnitPriceMinor());
                    $existing->setActualUnitPriceCurrency($newLine->getActualUnitPriceCurrency());
                    $existing->setNote($newLine->getNote());
                } else {
                    $managed->addLine($newLine);
                }
                unset($managedLinesById[$lineId]);
            }
            // Remove lines no longer present in the domain aggregate
            foreach ($managedLinesById as $orphanLine) {
                $managed->getLines()->removeElement($orphanLine);
                $this->em->remove($orphanLine);
            }

            $this->em->flush();
        } else {
            $this->em->persist($entity);
            $this->em->flush();
        }
    }

    public function findById(SupplierReceiptId $id): ?SupplierReceipt
    {
        $entity = $this->em->find(SupplierReceiptEntity::class, Uuid::fromString($id->toString()));

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByDeliveryNoteReference(SupplierId $supplierId, DeliveryNoteReference $ref): ?SupplierReceipt
    {
        $entity = $this->em->getRepository(SupplierReceiptEntity::class)->findOneBy([
            'supplierId'            => Uuid::fromString($supplierId->toString()),
            'deliveryNoteReference' => $ref->toString(),
        ]);

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }
}
