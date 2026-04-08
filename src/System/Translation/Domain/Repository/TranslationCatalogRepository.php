<?php

declare(strict_types=1);

namespace App\System\Translation\Domain\Repository;

use App\System\Translation\Domain\TranslationCatalog;
use App\System\Translation\Domain\ValueObject\TranslationCatalogId;

interface TranslationCatalogRepository
{
    public function save(TranslationCatalog $catalog): void;

    public function find(TranslationCatalogId $id): ?TranslationCatalog;
}
