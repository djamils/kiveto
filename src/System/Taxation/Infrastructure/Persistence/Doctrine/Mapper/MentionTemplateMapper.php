<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\Taxation\Domain\Entity\MentionTemplate;
use App\System\Taxation\Domain\ValueObject\AnimalUsage;
use App\System\Taxation\Domain\ValueObject\ClinicLiabilityStatus;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\MentionTemplateCondition;
use App\System\Taxation\Domain\ValueObject\MentionTemplateId;
use App\System\Taxation\Domain\ValueObject\RegionCode;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\MentionTemplateEntity;
use Symfony\Component\Uid\Uuid;

final readonly class MentionTemplateMapper
{
    public function toDomain(MentionTemplateEntity $entity): MentionTemplate
    {
        $json = $entity->getConditionJson();

        /** @var list<string> $regionsRaw */
        $regionsRaw = $json['regions'] ?? [];

        /** @var list<string> $statusesRaw */
        $statusesRaw = $json['customer_statuses'] ?? [];

        /** @var list<string> $usagesRaw */
        $usagesRaw = $json['animal_usages'] ?? [];

        $regionsMatching  = array_map(static fn (string $r) => RegionCode::fromString($r), $regionsRaw);
        $customerStatuses = array_map(static fn (string $s) => CustomerTaxStatus::from($s), $statusesRaw);
        $animalUsages     = array_map(static fn (string $u) => AnimalUsage::from($u), $usagesRaw);

        /** @var string|null $clinicLiabilityRaw */
        $clinicLiabilityRaw = $json['clinic_liability'] ?? null;
        $clinicLiability    = null !== $clinicLiabilityRaw
            ? ClinicLiabilityStatus::from($clinicLiabilityRaw)
            : null;

        $condition = MentionTemplateCondition::of($regionsMatching, $customerStatuses, $animalUsages, $clinicLiability);

        return MentionTemplate::reconstitute(
            new MentionTemplateId($entity->getId()->toRfc4122()),
            TaxRegimeId::fromString($entity->getRegimeId()),
            $entity->getCode(),
            $condition,
            $entity->getTemplate(),
            $entity->getLocale(),
            $entity->isActive(),
            $entity->getCreatedAt(),
        );
    }

    public function toEntity(MentionTemplate $template): MentionTemplateEntity
    {
        $condition     = $template->condition();
        $conditionJson = [
            'regions'           => array_map(static fn (RegionCode $r) => $r->toString(), $condition->regionsMatching()),
            'customer_statuses' => array_map(static fn (CustomerTaxStatus $s) => $s->value, $condition->customerStatusesMatching()),
            'animal_usages'     => array_map(static fn (AnimalUsage $u) => $u->value, $condition->animalUsagesMatching()),
            'clinic_liability'  => $condition->clinicLiability()?->value,
        ];

        $entity = new MentionTemplateEntity();
        $entity->setId(Uuid::fromRfc4122($template->id()->toString()));
        $entity->setRegimeId($template->regimeId()->toString());
        $entity->setCode($template->code());
        $entity->setConditionJson($conditionJson);
        $entity->setTemplate($template->template());
        $entity->setLocale($template->locale());
        $entity->setActive($template->isActive());
        $entity->setCreatedAt($template->createdAt());

        return $entity;
    }
}
