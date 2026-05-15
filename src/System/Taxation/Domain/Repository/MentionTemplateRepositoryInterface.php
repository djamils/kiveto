<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Repository;

use App\System\Taxation\Domain\Entity\MentionTemplate;
use App\System\Taxation\Domain\ValueObject\MentionTemplateId;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

interface MentionTemplateRepositoryInterface
{
    public function save(MentionTemplate $template): void;

    public function findById(MentionTemplateId $id): ?MentionTemplate;

    /** @return list<MentionTemplate> */
    public function findActiveByRegime(TaxRegimeId $regimeId): array;

    public function deleteByRegime(TaxRegimeId $regimeId): void;
}
