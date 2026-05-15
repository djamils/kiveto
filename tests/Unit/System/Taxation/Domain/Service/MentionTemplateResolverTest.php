<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\Service;

use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\Entity\MentionTemplate;
use App\System\Taxation\Domain\Repository\MentionTemplateRepositoryInterface;
use App\System\Taxation\Domain\Service\MentionTemplateResolver;
use App\System\Taxation\Domain\ValueObject\AppliedTaxRate;
use App\System\Taxation\Domain\ValueObject\ClinicLiabilityStatus;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\MentionTemplateCondition;
use App\System\Taxation\Domain\ValueObject\MentionTemplateId;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use PHPUnit\Framework\TestCase;

final class MentionTemplateResolverTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
    }

    public function testMatchingIntracomConditionGeneratesMention(): void
    {
        $regimeId  = TaxRegimeId::fromString('FR');
        $condition = MentionTemplateCondition::of([], [CustomerTaxStatus::INTRACOM], [], null);
        $template  = $this->makeTemplate('INTRACOM_REVERSE_CHARGE', 'TVA non applicable', $condition, $regimeId);

        $repo = $this->createStub(MentionTemplateRepositoryInterface::class);
        $repo->method('findActiveByRegime')->willReturn([$template]);

        $resolver = new MentionTemplateResolver($repo);

        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->now,
            ClinicLiabilityStatus::LIABLE,
            null,
            CustomerTaxStatus::INTRACOM,
        );

        $mentions = $resolver->findApplicable($regimeId, $context, $this->makeAppliedRate(2000));

        self::assertSame(1, $mentions->count());
        $mention = $mentions->byCode('INTRACOM_REVERSE_CHARGE');
        self::assertNotNull($mention);
        self::assertSame('TVA non applicable', $mention->text);
    }

    public function testRateSubstituted(): void
    {
        $regimeId  = TaxRegimeId::fromString('FR');
        $condition = MentionTemplateCondition::of([], [], [], null);
        $template  = $this->makeTemplate('RATE_MENTION', 'Rate: {{rate}}', $condition, $regimeId);

        $repo = $this->createStub(MentionTemplateRepositoryInterface::class);
        $repo->method('findActiveByRegime')->willReturn([$template]);

        $resolver = new MentionTemplateResolver($repo);

        $context  = FiscalContext::minimal(CountryCode::fromString('FR'), $this->now);
        $mentions = $resolver->findApplicable($regimeId, $context, $this->makeAppliedRate(2000));

        self::assertSame(1, $mentions->count());
        $rateMention = $mentions->byCode('RATE_MENTION');
        self::assertNotNull($rateMention);
        self::assertSame('Rate: 20.00', $rateMention->text);
    }

    public function testRegimeSubstituted(): void
    {
        $regimeId  = TaxRegimeId::fromString('FR');
        $condition = MentionTemplateCondition::of([], [], [], null);
        $template  = $this->makeTemplate('REGIME_MENTION', '{{regime}} TVA', $condition, $regimeId);

        $repo = $this->createStub(MentionTemplateRepositoryInterface::class);
        $repo->method('findActiveByRegime')->willReturn([$template]);

        $resolver = new MentionTemplateResolver($repo);

        $context  = FiscalContext::minimal(CountryCode::fromString('FR'), $this->now);
        $mentions = $resolver->findApplicable($regimeId, $context, $this->makeAppliedRate(2000));

        $regimeMention = $mentions->byCode('REGIME_MENTION');
        self::assertNotNull($regimeMention);
        self::assertSame('FR TVA', $regimeMention->text);
    }

    public function testCountrySubstituted(): void
    {
        $regimeId  = TaxRegimeId::fromString('FR');
        $condition = MentionTemplateCondition::of([], [], [], null);
        $template  = $this->makeTemplate('COUNTRY_MENTION', '{{country}}', $condition, $regimeId);

        $repo = $this->createStub(MentionTemplateRepositoryInterface::class);
        $repo->method('findActiveByRegime')->willReturn([$template]);

        $resolver = new MentionTemplateResolver($repo);

        $context  = FiscalContext::minimal(CountryCode::fromString('FR'), $this->now);
        $mentions = $resolver->findApplicable($regimeId, $context, $this->makeAppliedRate(2000));

        $countryMention = $mentions->byCode('COUNTRY_MENTION');
        self::assertNotNull($countryMention);
        self::assertSame('FR', $countryMention->text);
    }

    public function testUnknownPlaceholderLeftAsIs(): void
    {
        $regimeId  = TaxRegimeId::fromString('FR');
        $condition = MentionTemplateCondition::of([], [], [], null);
        $template  = $this->makeTemplate('UNKNOWN_PLACEHOLDER', '{{foobar}}', $condition, $regimeId);

        $repo = $this->createStub(MentionTemplateRepositoryInterface::class);
        $repo->method('findActiveByRegime')->willReturn([$template]);

        $resolver = new MentionTemplateResolver($repo);

        $context  = FiscalContext::minimal(CountryCode::fromString('FR'), $this->now);
        $mentions = $resolver->findApplicable($regimeId, $context, $this->makeAppliedRate(2000));

        $unknownMention = $mentions->byCode('UNKNOWN_PLACEHOLDER');
        self::assertNotNull($unknownMention);
        self::assertSame('{{foobar}}', $unknownMention->text);
    }

    public function testNonMatchingConditionExcluded(): void
    {
        $regimeId  = TaxRegimeId::fromString('FR');
        $condition = MentionTemplateCondition::of([], [CustomerTaxStatus::EXPORT], [], null);
        $template  = $this->makeTemplate('EXPORT_MENTION', 'Export mention', $condition, $regimeId);

        $repo = $this->createStub(MentionTemplateRepositoryInterface::class);
        $repo->method('findActiveByRegime')->willReturn([$template]);

        $resolver = new MentionTemplateResolver($repo);

        // Context is B2C, filter requires EXPORT
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->now,
            ClinicLiabilityStatus::LIABLE,
            null,
            CustomerTaxStatus::B2C,
        );

        $mentions = $resolver->findApplicable($regimeId, $context, $this->makeAppliedRate(2000));

        self::assertTrue($mentions->isEmpty());
    }

    private function makeTemplate(
        string $code,
        string $template,
        MentionTemplateCondition $condition,
        TaxRegimeId $regimeId,
    ): MentionTemplate {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($this->now);

        return MentionTemplate::create(
            new MentionTemplateId('00000000-0000-0000-0000-000000000001'),
            $regimeId,
            $code,
            $condition,
            $template,
            'fr_FR',
            $clock,
        );
    }

    private function makeAppliedRate(int $basisPoints): AppliedTaxRate
    {
        return AppliedTaxRate::of(
            TaxRateId::fromString('fr.standard'),
            TaxRegimeId::fromString('FR'),
            TaxRateValue::ofBasisPoints($basisPoints),
            $this->now,
            'yaml.fr.standard',
        );
    }
}
