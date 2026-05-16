<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ReadModel;

use App\System\PharmaceuticalRegistry\Application\ReadModel\PharmaceuticalRefView;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\AuthorizationEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\CompositionEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\JurisdictionEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\PresentationEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class PharmaceuticalRefProjection
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findById(string $id): ?PharmaceuticalRefView
    {
        $entity = $this->em->find(AuthorizationEntity::class, Uuid::fromString($id));

        if (null === $entity) {
            return null;
        }

        return $this->hydrate($entity);
    }

    public function findByGtin(string $gtin): ?PharmaceuticalRefView
    {
        $presentation = $this->em->getRepository(PresentationEntity::class)
            ->findOneBy(['gtin' => $gtin])
        ;

        if (null === $presentation) {
            return null;
        }

        return $this->hydrate($presentation->getAuthorization());
    }

    public function findByJurisdictionalId(string $jurisdiction, string $authority, string $identifier): ?PharmaceuticalRefView
    {
        $jurisdictionEntity = $this->em->getRepository(JurisdictionEntity::class)
            ->findOneBy([
                'jurisdictionCode'    => $jurisdiction,
                'authorityCode'       => $authority,
                'authorityIdentifier' => $identifier,
            ])
        ;

        if (null === $jurisdictionEntity) {
            return null;
        }

        return $this->hydrate($jurisdictionEntity->getAuthorization());
    }

    private function hydrate(AuthorizationEntity $entity): PharmaceuticalRefView
    {
        $presentations = $entity->getPresentations()->map(
            static fn (PresentationEntity $p) => [
                'id'                   => $p->getId()->toString(),
                'description'          => $p->getDescription(),
                'gtin'                 => $p->getGtin(),
                'prescriptionRequired' => $p->isPrescriptionRequired(),
            ],
        )->toArray();

        $activeSubstanceLabels = $entity->getCompositions()->map(
            static fn (CompositionEntity $c) => $c->getActiveSubstance()->getLabel(),
        )->toArray();

        return new PharmaceuticalRefView(
            id: $entity->getId()->toString(),
            commercialName: $entity->getCommercialName(),
            holderLaboratory: $entity->getHolderLaboratory(),
            status: $entity->getStatus(),
            atcVetCode: $entity->getAtcVetCode(),
            permanentIdentifier: $entity->getPermanentIdentifier(),
            presentations: $presentations,
            activeSubstanceLabels: $activeSubstanceLabels,
            pharmaceuticalForm: $entity->getPharmaceuticalForm(),
            lastImportSource: $entity->getLastImportSource(),
            lastImportedAt: $entity->getLastImportedAt(),
        );
    }
}
