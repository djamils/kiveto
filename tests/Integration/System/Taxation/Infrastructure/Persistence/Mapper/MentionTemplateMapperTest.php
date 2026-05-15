<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Persistence\Mapper;

use App\System\Taxation\Domain\Entity\MentionTemplate;
use App\System\Taxation\Domain\ValueObject\ClinicLiabilityStatus;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\MentionTemplateCondition;
use App\System\Taxation\Domain\ValueObject\MentionTemplateId;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\MentionTemplateEntity;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper\MentionTemplateMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MentionTemplateMapperTest extends TestCase
{
    private MentionTemplateMapper $mapper;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->mapper = new MentionTemplateMapper();
        $this->now    = new \DateTimeImmutable('2026-05-16T10:00:00+00:00');
    }

    public function testRoundTripSymmetry(): void
    {
        $id        = new MentionTemplateId(Uuid::v4()->toRfc4122());
        $regimeId  = TaxRegimeId::fromString('FR');
        $condition = MentionTemplateCondition::of([], [CustomerTaxStatus::INTRACOM], [], null);

        $original = MentionTemplate::reconstitute(
            $id,
            $regimeId,
            'INTRACOM_REVERSE_CHARGE',
            $condition,
            'TVA non applicable, article 262 ter, I du CGI',
            'fr_FR',
            true,
            $this->now,
        );

        $entity        = $this->mapper->toEntity($original);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($id->toString(), $reconstituted->id()->toString());
        self::assertSame('FR', $reconstituted->regimeId()->toString());
        self::assertSame('INTRACOM_REVERSE_CHARGE', $reconstituted->code());
        self::assertSame('TVA non applicable, article 262 ter, I du CGI', $reconstituted->template());
        self::assertSame('fr_FR', $reconstituted->locale());
        self::assertTrue($reconstituted->isActive());
        self::assertSame($this->now->getTimestamp(), $reconstituted->createdAt()->getTimestamp());
    }

    public function testToEntityPreservesUuidId(): void
    {
        $uuid      = Uuid::v4();
        $id        = new MentionTemplateId($uuid->toRfc4122());
        $condition = MentionTemplateCondition::of([], [], [], null);

        $template = MentionTemplate::reconstitute(
            $id,
            TaxRegimeId::fromString('FR'),
            'EXPORT',
            $condition,
            'Exportation hors UE',
            'fr_FR',
            false,
            $this->now,
        );

        $entity = $this->mapper->toEntity($template);

        self::assertSame($uuid->toRfc4122(), $entity->getId()->toRfc4122());
        self::assertFalse($entity->isActive());
        self::assertSame($this->now->getTimestamp(), $entity->getCreatedAt()->getTimestamp());
    }

    public function testToDomainWithClinicLiabilitySet(): void
    {
        $uuid   = Uuid::v4();
        $entity = new MentionTemplateEntity();
        $entity->setId($uuid);
        $entity->setRegimeId('FR');
        $entity->setCode('NOT_LIABLE_MENTION');
        $entity->setConditionJson([
            'regions'           => [],
            'customer_statuses' => [],
            'animal_usages'     => [],
            'clinic_liability'  => 'not_liable',
        ]);
        $entity->setTemplate('Exonération article 261 CGI');
        $entity->setLocale('fr_FR');
        $entity->setActive(true);
        $entity->setCreatedAt($this->now);

        $domain = $this->mapper->toDomain($entity);

        self::assertSame('NOT_LIABLE_MENTION', $domain->code());
        self::assertSame(ClinicLiabilityStatus::NOT_LIABLE, $domain->condition()->clinicLiability());
    }
}
