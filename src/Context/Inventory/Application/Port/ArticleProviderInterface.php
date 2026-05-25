<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;

interface ArticleProviderInterface
{
    public function exists(ArticleId $articleId, ClinicId $clinicId): bool;

    public function isActive(ArticleId $articleId, ClinicId $clinicId): bool;

    public function getUnitOfMeasure(ArticleId $articleId, ClinicId $clinicId): UnitOfMeasure;

    public function getName(ArticleId $articleId, ClinicId $clinicId): string;
}
