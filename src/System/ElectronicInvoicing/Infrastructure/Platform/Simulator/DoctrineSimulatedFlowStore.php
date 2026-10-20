<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\SimulatedFlowEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSimulatedFlowStore implements SimulatedFlowStoreInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(SimulatedFlow $flow): void
    {
        $statuses = array_map(static fn (SimulatedStatus $status): array => $status->toArray(), $flow->statuses());
        $entity   = $this->entityManager->find(SimulatedFlowEntity::class, $flow->flowId);

        if (null === $entity) {
            $entity = new SimulatedFlowEntity(
                flowId: $flow->flowId,
                organisationId: $flow->organisationId,
                trackingId: $flow->trackingId,
                sha256: $flow->sha256,
                documentNumber: $flow->documentNumber,
                typeCode: $flow->typeCode,
                issueDate: $flow->issueDate,
                sellerSiren: $flow->sellerSiren,
                buyerSiren: $flow->buyerSiren,
                scenario: $flow->scenario->value,
                file: $flow->file,
                createdAt: $flow->createdAt,
                statuses: $statuses,
                nextStep: $flow->nextStep(),
                updatedAt: $flow->updatedAt(),
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->record($statuses, $flow->nextStep(), $flow->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function find(string $flowId): ?SimulatedFlow
    {
        $entity = $this->entityManager->find(SimulatedFlowEntity::class, $flowId);

        return null === $entity ? null : self::toFlow($entity);
    }

    public function findByDeposit(string $trackingId, string $sha256): ?SimulatedFlow
    {
        $entity = $this->repository()->findOneBy(['trackingId' => $trackingId, 'sha256' => $sha256]);

        return null === $entity ? null : self::toFlow($entity);
    }

    public function findByDocument(string $sellerSiren, string $documentNumber): ?SimulatedFlow
    {
        $entity = $this->repository()->findOneBy(['sellerSiren' => $sellerSiren, 'documentNumber' => $documentNumber], ['createdAt' => 'DESC', 'flowId' => 'DESC']);

        return null === $entity ? null : self::toFlow($entity);
    }

    public function forOrganisation(string $organisationId): array
    {
        return array_map(self::toFlow(...), $this->repository()->findBy(['organisationId' => $organisationId], ['flowId' => 'ASC']));
    }

    public function pending(): array
    {
        $entities = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(SimulatedFlowEntity::class, 'f')
            ->where('f.nextStep IS NOT NULL')
            ->orderBy('f.updatedAt', 'ASC')
            ->addOrderBy('f.flowId', 'ASC')
            ->getQuery()
            ->getResult()
        ;
        \assert(\is_array($entities));

        return array_values(array_map(static function (mixed $entity): SimulatedFlow {
            \assert($entity instanceof SimulatedFlowEntity);

            return self::toFlow($entity);
        }, $entities));
    }

    /**
     * @return \Doctrine\ORM\EntityRepository<SimulatedFlowEntity>
     */
    private function repository(): \Doctrine\ORM\EntityRepository
    {
        return $this->entityManager->getRepository(SimulatedFlowEntity::class);
    }

    private static function toFlow(SimulatedFlowEntity $entity): SimulatedFlow
    {
        $utc = new \DateTimeZone('UTC');

        return new SimulatedFlow(
            flowId: $entity->getFlowId(),
            organisationId: $entity->getOrganisationId(),
            trackingId: $entity->getTrackingId(),
            sha256: $entity->getSha256(),
            documentNumber: $entity->getDocumentNumber(),
            typeCode: $entity->getTypeCode(),
            issueDate: $entity->getIssueDate(),
            sellerSiren: $entity->getSellerSiren(),
            buyerSiren: $entity->getBuyerSiren(),
            scenario: SimulationScenario::from($entity->getScenario()),
            file: $entity->getFile(),
            createdAt: new \DateTimeImmutable($entity->getCreatedAt()->format('Y-m-d H:i:s'), $utc),
            statuses: array_map(SimulatedStatus::fromArray(...), $entity->getStatuses()),
            nextStep: $entity->getNextStep(),
            updatedAt: new \DateTimeImmutable($entity->getUpdatedAt()->format('Y-m-d H:i:s'), $utc),
        );
    }
}
