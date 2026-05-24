<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(name: 'idx_pkg_component_package', columns: ['package_id'])]
class PackageComponentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'package_id', type: UuidType::NAME)]
    private Uuid $packageId;

    #[ORM\Column(name: 'item_type', type: 'string', length: 10)]
    private string $itemType;

    #[ORM\Column(name: 'item_id', type: 'string', length: 36)]
    private string $itemId;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'integer')]
    private int $weight;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getPackageId(): Uuid
    {
        return $this->packageId;
    }

    public function setPackageId(Uuid $packageId): void
    {
        $this->packageId = $packageId;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): void
    {
        $this->itemType = $itemType;
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function setItemId(string $itemId): void
    {
        $this->itemId = $itemId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): void
    {
        $this->weight = $weight;
    }
}
