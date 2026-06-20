<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Domain\PurchaseOrder\Exception\ConcurrentModificationException;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\PurchaseOrderEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\PurchaseOrderMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PurchaseOrderMapper $mapper,
    ) {
    }

    public function save(PurchaseOrder $order): void
    {
        try {
            $entity  = $this->mapper->toEntity($order);
            $managed = $this->em->find(PurchaseOrderEntity::class, $entity->getId());

            if (null !== $managed) {
                // Update the managed entity in-place to avoid EntityIdentityCollisionException.
                // This occurs when findById() loads an entity into the EM identity map and then
                // save() tries to persist a new entity object with the same primary key.
                // Lines are synced by ID: existing managed lines are updated, new lines are added.
                $managed->setStatus($entity->getStatus());
                $managed->setExternalReferenceValue($entity->getExternalReferenceValue());
                $managed->setExternalReferenceProvidedBy($entity->getExternalReferenceProvidedBy());
                $managed->setDeliveryAddressJson($entity->getDeliveryAddressJson());
                $managed->setPdfFileId($entity->getPdfFileId());
                $managed->setSubmittedAt($entity->getSubmittedAt());
                $managed->setConfirmedAt($entity->getConfirmedAt());
                $managed->setUpdatedAt($entity->getUpdatedAt());

                // Sync lines: update existing managed line entities, add new ones
                $managedLinesById = [];
                foreach ($managed->getLines() as $managedLine) {
                    $managedLinesById[$managedLine->getId()->toRfc4122()] = $managedLine;
                }
                foreach ($entity->getLines() as $newLine) {
                    $lineId = $newLine->getId()->toRfc4122();
                    if (isset($managedLinesById[$lineId])) {
                        $existing = $managedLinesById[$lineId];
                        $existing->setOrderedAmount($newLine->getOrderedAmount());
                        $existing->setOrderedUnit($newLine->getOrderedUnit());
                        $existing->setUnitPriceMinor($newLine->getUnitPriceMinor());
                        $existing->setUnitPriceCurrency($newLine->getUnitPriceCurrency());
                        $existing->setReceivedAmount($newLine->getReceivedAmount());
                        $existing->setStatus($newLine->getStatus());
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
        } catch (OptimisticLockException) {
            throw new ConcurrentModificationException($order->id()->toString());
        }
    }

    public function findById(PurchaseOrderId $id): ?PurchaseOrder
    {
        $entity = $this->em->find(PurchaseOrderEntity::class, Uuid::fromString($id->toString()));

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByClinicAndNumber(ClinicId $clinicId, PurchaseOrderNumber $number): ?PurchaseOrder
    {
        $entity = $this->em->getRepository(PurchaseOrderEntity::class)->findOneBy([
            'clinicId'    => Uuid::fromString($clinicId->toString()),
            'orderNumber' => $number->toString(),
        ]);

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }
}
