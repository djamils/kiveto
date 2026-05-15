<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\RegimeLoader;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Application\Port\TaxRegimeBlueprint;
use App\System\Taxation\Application\Port\TaxRegimeLoaderInterface;
use App\System\Taxation\Domain\Entity\MentionTemplate;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\Exception\OverlappingValidityPeriodsException;
use App\System\Taxation\Domain\Exception\UnknownCategoryPatternException;
use App\System\Taxation\Domain\Repository\TaxCategoryRepositoryInterface;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\ValueObject\AnimalUsage;
use App\System\Taxation\Domain\ValueObject\ClinicLiabilityStatus;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\MentionTemplateCondition;
use App\System\Taxation\Domain\ValueObject\MentionTemplateId;
use App\System\Taxation\Domain\ValueObject\RegionCode;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlTaxRegimeLoader implements TaxRegimeLoaderInterface
{
    public function __construct(
        private RegimeFileLocator $locator,
        private TaxCategoryRegistryInterface $categoryRegistry,
        private TaxCategoryRepositoryInterface $categoryRepo,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function load(TaxRegimeId $regimeId): TaxRegimeBlueprint
    {
        $path = $this->locator->locate($regimeId);

        /** @var array<string, mixed> $data */
        $data = Yaml::parseFile($path);

        /** @var string $countryRaw */
        $countryRaw = $data['country'];
        $country    = CountryCode::fromString($countryRaw);

        /** @var list<TaxRate> $rates */
        $rates = [];

        /** @var list<array<string, mixed>> $ratesData */
        $ratesData = $data['rates'] ?? [];

        foreach ($ratesData as $rateData) {
            /** @var array<string, mixed> $appliesTo */
            $appliesTo = $rateData['appliesTo'];

            /** @var array<string, mixed> $conditions */
            $conditions = $appliesTo['conditions'] ?? [];

            /** @var list<string> $categoriesMatching */
            $categoriesMatching = $appliesTo['categories'] ?? [];

            /** @var list<string> $regionsRaw */
            $regionsRaw = $conditions['regions'] ?? [];

            /** @var list<string> $statusesRaw */
            $statusesRaw = $conditions['customerStatuses'] ?? [];

            /** @var list<string> $usagesRaw */
            $usagesRaw = $conditions['animalUsages'] ?? [];

            $regionsMatching  = array_map(static fn (string $r) => RegionCode::fromString($r), $regionsRaw);
            $customerStatuses = array_map(static fn (string $s) => CustomerTaxStatus::from($s), $statusesRaw);
            $animalUsages     = array_map(static fn (string $u) => AnimalUsage::from($u), $usagesRaw);

            /** @var string|null $clinicLiabilityRaw */
            $clinicLiabilityRaw = $conditions['clinicLiability'] ?? null;
            $clinicLiability    = null !== $clinicLiabilityRaw
                ? ClinicLiabilityStatus::from($clinicLiabilityRaw)
                : null;

            foreach ($categoriesMatching as $pattern) {
                if (str_contains($pattern, '*')) {
                    $found = false;

                    foreach ($this->categoryRepo->findAllActive() as $category) {
                        if (fnmatch($pattern, $category->code()->toString())) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        throw new UnknownCategoryPatternException($regimeId->toString(), $pattern);
                    }
                } else {
                    $code = TaxCategoryCode::fromString($pattern);

                    if (!$this->categoryRegistry->has($code)) {
                        throw new UnknownCategoryPatternException($regimeId->toString(), $pattern);
                    }
                }
            }

            $condition = TaxRateCondition::of(
                $categoriesMatching,
                $regionsMatching,
                $customerStatuses,
                $animalUsages,
                $clinicLiability,
            );

            /** @var string $validFromRaw */
            $validFromRaw = $rateData['validFrom'];
            $validFrom    = new \DateTimeImmutable($validFromRaw);

            /** @var string|null $validToRaw */
            $validToRaw = $rateData['validTo'] ?? null;
            $validTo    = null !== $validToRaw ? new \DateTimeImmutable($validToRaw) : null;
            $validity   = null === $validTo
                ? ValidityPeriod::openEndedFrom($validFrom)
                : ValidityPeriod::between($validFrom, $validTo);

            /** @var string $rateIdRaw */
            $rateIdRaw = $rateData['id'];
            /** @var string $rateValueRaw */
            $rateValueRaw = $rateData['value'];

            $rates[] = TaxRate::of(
                TaxRateId::fromString($rateIdRaw),
                TaxRateValue::ofPercentString($rateValueRaw),
                $validity,
                $condition,
            );
        }

        $count = \count($rates);

        for ($i = 0; $i < $count; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                $rateA = $rates[$i];
                $rateB = $rates[$j];

                if (!$rateA->appliesTo()->equals($rateB->appliesTo())) {
                    continue;
                }

                if ($this->periodsOverlap($rateA->validityPeriod(), $rateB->validityPeriod())) {
                    throw new OverlappingValidityPeriodsException(
                        $regimeId->toString(),
                        $rateA->id()->toString(),
                        $rateB->id()->toString(),
                    );
                }
            }
        }

        /** @var list<MentionTemplate> $mentionTemplates */
        $mentionTemplates = [];

        /** @var list<array<string, mixed>> $tplsData */
        $tplsData = $data['mentionTemplates'] ?? [];

        foreach ($tplsData as $tplData) {
            /** @var array<string, mixed> $cond */
            $cond = $tplData['condition'] ?? [];

            /** @var list<string> $regionsRaw */
            $regionsRaw = $cond['regions'] ?? [];

            /** @var list<string> $statusesRaw */
            $statusesRaw = $cond['customerStatuses'] ?? [];

            /** @var list<string> $usagesRaw */
            $usagesRaw = $cond['animalUsages'] ?? [];

            $regionsM  = array_map(static fn (string $r) => RegionCode::fromString($r), $regionsRaw);
            $statusesM = array_map(static fn (string $s) => CustomerTaxStatus::from($s), $statusesRaw);
            $usagesM   = array_map(static fn (string $u) => AnimalUsage::from($u), $usagesRaw);

            /** @var string|null $clinicLiabilityRaw */
            $clinicLiabilityRaw = $cond['clinicLiability'] ?? null;
            $clinicL            = null !== $clinicLiabilityRaw
                ? ClinicLiabilityStatus::from($clinicLiabilityRaw)
                : null;

            $condition = MentionTemplateCondition::of($regionsM, $statusesM, $usagesM, $clinicL);

            /** @var string $tplCode */
            $tplCode = $tplData['code'];
            /** @var string $tplTemplate */
            $tplTemplate = $tplData['template'];
            /** @var string $tplLocale */
            $tplLocale = $tplData['locale'];

            $mentionTemplates[] = MentionTemplate::create(
                new MentionTemplateId($this->uuidGenerator->generate()),
                $regimeId,
                $tplCode,
                $condition,
                $tplTemplate,
                $tplLocale,
                $this->clock,
            );
        }

        /** @var string $nameRaw */
        $nameRaw = $data['name'];
        /** @var bool $activeRaw */
        $activeRaw = $data['active'] ?? false;

        return new TaxRegimeBlueprint(
            $regimeId,
            $nameRaw,
            $country,
            $activeRaw,
            $rates,
            $mentionTemplates,
        );
    }

    private function periodsOverlap(ValidityPeriod $a, ValidityPeriod $b): bool
    {
        $aTo = $a->validTo();
        $bTo = $b->validTo();

        $aFromLeqBTo = null === $bTo || $a->validFrom() <= $bTo;
        $bFromLeqATo = null === $aTo || $b->validFrom() <= $aTo;

        return $aFromLeqBTo && $bFromLeqATo;
    }
}
