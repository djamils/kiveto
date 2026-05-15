<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Persistence;

use App\Fixtures\System\Taxation\Factory\MentionTemplateEntityFactory;
use App\System\Taxation\Domain\Entity\MentionTemplate;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\MentionTemplateCondition;
use App\System\Taxation\Domain\ValueObject\MentionTemplateId;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Repository\DoctrineMentionTemplateRepository;
use App\Tests\Shared\Time\FrozenClock;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineMentionTemplateRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSaveThenFindActiveByRegime(): void
    {
        $clock      = new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $regimeId   = TaxRegimeId::fromString('FR');
        $templateId = new MentionTemplateId(Uuid::v4()->toRfc4122());
        $condition  = MentionTemplateCondition::of([], [CustomerTaxStatus::INTRACOM], [], null);

        $template = MentionTemplate::create(
            $templateId,
            $regimeId,
            'INTRACOM_REVERSE_CHARGE',
            $condition,
            'TVA non applicable, article 262 ter, I du CGI',
            'fr_FR',
            $clock,
        );

        $repo = $this->getRepository();
        $repo->save($template);

        $active = $repo->findActiveByRegime($regimeId);

        self::assertCount(1, $active);
        self::assertSame($templateId->toString(), $active[0]->id()->toString());
        self::assertSame('FR', $active[0]->regimeId()->toString());
        self::assertSame('INTRACOM_REVERSE_CHARGE', $active[0]->code());
        self::assertSame('TVA non applicable, article 262 ter, I du CGI', $active[0]->template());
        self::assertSame('fr_FR', $active[0]->locale());
        self::assertTrue($active[0]->isActive());
    }

    public function testFindActiveByRegimeExcludesInactive(): void
    {
        $now      = new \DateTimeImmutable();
        $regimeId = TaxRegimeId::fromString('FR');

        MentionTemplateEntityFactory::createOne([
            'regimeId'      => 'FR',
            'code'          => 'INTRACOM_REVERSE_CHARGE',
            'active'        => true,
            'locale'        => 'fr_FR',
            'template'      => 'TVA non applicable, article 262 ter, I du CGI',
            'conditionJson' => [
                'regions'           => [],
                'customer_statuses' => ['intracom'],
                'animal_usages'     => [],
                'clinic_liability'  => null,
            ],
            'createdAt' => $now,
        ]);
        MentionTemplateEntityFactory::createOne([
            'regimeId'      => 'FR',
            'code'          => 'EXPORT_OUT_OF_EU',
            'active'        => false,
            'locale'        => 'fr_FR',
            'template'      => 'Exportation hors UE — exonération TVA article 262 I du CGI',
            'conditionJson' => [
                'regions'           => [],
                'customer_statuses' => ['export'],
                'animal_usages'     => [],
                'clinic_liability'  => null,
            ],
            'createdAt' => $now,
        ]);

        $repo   = $this->getRepository();
        $active = $repo->findActiveByRegime($regimeId);

        self::assertCount(1, $active);
        self::assertSame('INTRACOM_REVERSE_CHARGE', $active[0]->code());
    }

    public function testFindActiveByRegimeFiltersCorrectlyByRegimeId(): void
    {
        $now = new \DateTimeImmutable();

        MentionTemplateEntityFactory::createOne([
            'regimeId'      => 'FR',
            'code'          => 'FR_TEMPLATE',
            'active'        => true,
            'locale'        => 'fr_FR',
            'template'      => 'Template for FR',
            'conditionJson' => [
                'regions'           => [],
                'customer_statuses' => [],
                'animal_usages'     => [],
                'clinic_liability'  => null,
            ],
            'createdAt' => $now,
        ]);
        MentionTemplateEntityFactory::createOne([
            'regimeId'      => 'BE',
            'code'          => 'BE_TEMPLATE',
            'active'        => true,
            'locale'        => 'fr_BE',
            'template'      => 'Template for BE',
            'conditionJson' => [
                'regions'           => [],
                'customer_statuses' => [],
                'animal_usages'     => [],
                'clinic_liability'  => null,
            ],
            'createdAt' => $now,
        ]);

        $repo   = $this->getRepository();
        $active = $repo->findActiveByRegime(TaxRegimeId::fromString('FR'));

        self::assertCount(1, $active);
        self::assertSame('FR_TEMPLATE', $active[0]->code());
    }

    public function testDeleteByRegimeRemovesAll(): void
    {
        $now      = new \DateTimeImmutable();
        $regimeId = TaxRegimeId::fromString('FR');

        MentionTemplateEntityFactory::createOne([
            'regimeId'      => 'FR',
            'code'          => 'INTRACOM_REVERSE_CHARGE',
            'active'        => true,
            'locale'        => 'fr_FR',
            'template'      => 'TVA non applicable',
            'conditionJson' => [
                'regions'           => [],
                'customer_statuses' => ['intracom'],
                'animal_usages'     => [],
                'clinic_liability'  => null,
            ],
            'createdAt' => $now,
        ]);
        MentionTemplateEntityFactory::createOne([
            'regimeId'      => 'FR',
            'code'          => 'EXPORT_OUT_OF_EU',
            'active'        => true,
            'locale'        => 'fr_FR',
            'template'      => 'Exportation hors UE',
            'conditionJson' => [
                'regions'           => [],
                'customer_statuses' => ['export'],
                'animal_usages'     => [],
                'clinic_liability'  => null,
            ],
            'createdAt' => $now,
        ]);

        $repo = $this->getRepository();

        self::assertCount(2, $repo->findActiveByRegime($regimeId));

        $repo->deleteByRegime($regimeId);

        self::assertCount(0, $repo->findActiveByRegime($regimeId));
    }

    public function testSaveThenFindById(): void
    {
        $clock      = new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $regimeId   = TaxRegimeId::fromString('FR');
        $templateId = new MentionTemplateId(Uuid::v4()->toRfc4122());
        $condition  = MentionTemplateCondition::of([], [], [], null);

        $template = MentionTemplate::create(
            $templateId,
            $regimeId,
            'GENERIC',
            $condition,
            'Template générique',
            'fr_FR',
            $clock,
        );

        $repo = $this->getRepository();
        $repo->save($template);

        $found = $repo->findById($templateId);

        self::assertNotNull($found);
        self::assertSame($templateId->toString(), $found->id()->toString());
        self::assertSame('GENERIC', $found->code());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $repo  = $this->getRepository();
        $found = $repo->findById(new MentionTemplateId(Uuid::v4()->toRfc4122()));

        self::assertNull($found);
    }

    public function testSaveUpdatesExistingTemplate(): void
    {
        $clock      = new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $regimeId   = TaxRegimeId::fromString('FR');
        $templateId = new MentionTemplateId(Uuid::v4()->toRfc4122());
        $condition  = MentionTemplateCondition::of([], [], [], null);

        // First save: insert
        $template = MentionTemplate::create(
            $templateId,
            $regimeId,
            'ORIGINAL_CODE',
            $condition,
            'Original template',
            'fr_FR',
            $clock,
        );

        $repo = $this->getRepository();
        $repo->save($template);

        // Second save: update (same id, different code and template text)
        $updated = MentionTemplate::reconstitute(
            $templateId,
            $regimeId,
            'UPDATED_CODE',
            $condition,
            'Updated template',
            'fr_FR',
            true,
            new \DateTimeImmutable('2026-01-15T10:00:00+00:00'),
        );

        $repo->save($updated);

        $found = $repo->findById($templateId);

        self::assertNotNull($found);
        self::assertSame('UPDATED_CODE', $found->code());
        self::assertSame('Updated template', $found->template());
    }

    private function getRepository(): DoctrineMentionTemplateRepository
    {
        $repo = static::getContainer()->get(DoctrineMentionTemplateRepository::class);
        \assert($repo instanceof DoctrineMentionTemplateRepository);

        return $repo;
    }
}
