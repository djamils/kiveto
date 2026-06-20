<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Port;

use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;

interface ArticleProviderInterface
{
    public function exists(ArticleId $articleId, ClinicId $clinicId): bool;

    public function isActive(ArticleId $articleId, ClinicId $clinicId): bool;
}
