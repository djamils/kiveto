<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Catalog\ApplyStarterCatalog;

use App\Context\Catalog\Application\Command\Act\CreateAct\CreateAct;
use App\Context\Catalog\Application\Command\Article\CreateDrugArticle\CreateDrugArticle;
use App\Context\Catalog\Application\Command\Article\CreateNonDrugArticle\CreateNonDrugArticle;
use App\Context\Catalog\Application\Command\Pricing\CreatePriceList\CreatePriceList;
use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Application\Service\StarterCatalogApplicationReport;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Exception\StarterCatalogNotAvailableException;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\CommandBusInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Yaml\Yaml;

#[AsMessageHandler]
final class ApplyStarterCatalogHandler
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ClinicInfoProviderInterface $clinicInfoProvider,
        private readonly ActRepositoryInterface $actRepository,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly PriceListRepositoryInterface $priceListRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ApplyStarterCatalog $command): StarterCatalogApplicationReport
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $countryCode = $this->clinicInfoProvider->getCountryCode($clinicId)->toString();
        $currency    = $this->clinicInfoProvider->getCurrencyCode($clinicId)->toString();

        $path = \sprintf(
            '%s/../../Infrastructure/Resources/starter-catalogs/%s/%s.yaml',
            __DIR__,
            strtolower($countryCode),
            $command->catalogType,
        );

        $realPath = realpath($path);

        if (false === $realPath || !file_exists($realPath)) {
            throw new StarterCatalogNotAvailableException($countryCode, $command->catalogType);
        }

        $raw = Yaml::parseFile($realPath);

        if (!\is_array($raw)) {
            throw new StarterCatalogNotAvailableException($countryCode, $command->catalogType);
        }

        /** @var list<array<string, mixed>> $acts */
        $acts = \is_array($raw['acts'] ?? null) ? $raw['acts'] : [];
        /** @var list<array<string, mixed>> $drugArticles */
        $drugArticles = \is_array($raw['drug_articles'] ?? null) ? $raw['drug_articles'] : [];
        /** @var list<array<string, mixed>> $nonDrugArticles */
        $nonDrugArticles = \is_array($raw['non_drug_articles'] ?? null) ? $raw['non_drug_articles'] : [];
        /** @var list<array<string, mixed>> $priceLists */
        $priceLists = \is_array($raw['price_lists'] ?? null) ? $raw['price_lists'] : [];

        /** @var list<string> $created */
        $created = [];
        /** @var list<string> $skipped */
        $skipped = [];
        /** @var list<string> $failed */
        $failed = [];

        // Create acts
        foreach ($acts as $actData) {
            $code = isset($actData['code']) && \is_string($actData['code']) ? $actData['code'] : '';

            try {
                $actCode = ActCode::fromString($code);

                if ($this->actRepository->existsByCode($actCode, $clinicId)) {
                    $skipped[] = 'act:' . $code;
                    continue;
                }

                $this->commandBus->dispatch(new CreateAct(
                    clinicId: $command->clinicId,
                    name: isset($actData['name']) && \is_string($actData['name']) ? $actData['name'] : '',
                    code: $code,
                    description: null,
                    category: isset($actData['category']) && \is_string($actData['category']) ? $actData['category'] : '',
                    taxCategoryCode: isset($actData['tax_category_code']) && \is_string($actData['tax_category_code']) ? $actData['tax_category_code'] : '',
                    basePriceMinorUnits: isset($actData['base_price_minor_units']) && \is_int($actData['base_price_minor_units']) ? $actData['base_price_minor_units'] : 0,
                    basePriceCurrency: $currency,
                    estimatedDurationMinutes: isset($actData['estimated_duration_minutes']) && \is_int($actData['estimated_duration_minutes']) ? $actData['estimated_duration_minutes'] : 20,
                    requiresAnesthesia: isset($actData['requires_anesthesia']) && \is_bool($actData['requires_anesthesia']) && $actData['requires_anesthesia'],
                ));

                $created[] = 'act:' . $code;
            } catch (\Throwable $e) {
                $this->logger->warning(\sprintf('Failed to create act "%s": %s', $code, $e->getMessage()));
                $failed[] = 'act:' . $code;
            }
        }

        // Create drug articles
        foreach ($drugArticles as $articleData) {
            $code = isset($articleData['code']) && \is_string($articleData['code']) ? $articleData['code'] : '';

            try {
                $articleCode = ArticleCode::fromString($code);

                if ($this->articleRepository->existsByCode($articleCode, $clinicId)) {
                    $skipped[] = 'drug_article:' . $code;
                    continue;
                }

                $gtin              = isset($articleData['gtin']) && \is_string($articleData['gtin']) ? $articleData['gtin'] : null;
                $authRef           = isset($articleData['authorization_ref']) && \is_string($articleData['authorization_ref']) ? $articleData['authorization_ref'] : null;
                $prescriptionClass = isset($articleData['prescription_class']) && \is_string($articleData['prescription_class']) ? $articleData['prescription_class'] : null;

                $this->commandBus->dispatch(new CreateDrugArticle(
                    clinicId: $command->clinicId,
                    name: isset($articleData['name']) && \is_string($articleData['name']) ? $articleData['name'] : '',
                    code: $code,
                    gtin: $gtin,
                    taxCategoryCode: isset($articleData['tax_category_code']) && \is_string($articleData['tax_category_code']) ? $articleData['tax_category_code'] : '',
                    basePriceMinorUnits: isset($articleData['base_price_minor_units']) && \is_int($articleData['base_price_minor_units']) ? $articleData['base_price_minor_units'] : 0,
                    basePriceCurrency: $currency,
                    unitOfMeasure: isset($articleData['unit_of_measure']) && \is_string($articleData['unit_of_measure']) ? $articleData['unit_of_measure'] : 'UNIT',
                    authorizationRef: $authRef,
                    requiresPrescription: isset($articleData['requires_prescription']) && \is_bool($articleData['requires_prescription']) && $articleData['requires_prescription'],
                    prescriptionClass: $prescriptionClass,
                    isControlledSubstance: isset($articleData['is_controlled_substance']) && \is_bool($articleData['is_controlled_substance']) && $articleData['is_controlled_substance'],
                    trackStock: !isset($articleData['track_stock']) || !\is_bool($articleData['track_stock']) || $articleData['track_stock'],
                ));

                $created[] = 'drug_article:' . $code;
            } catch (\Throwable $e) {
                $this->logger->warning(\sprintf('Failed to create drug article "%s": %s', $code, $e->getMessage()));
                $failed[] = 'drug_article:' . $code;
            }
        }

        // Create non-drug articles
        foreach ($nonDrugArticles as $articleData) {
            $code = isset($articleData['code']) && \is_string($articleData['code']) ? $articleData['code'] : '';

            try {
                $articleCode = ArticleCode::fromString($code);

                if ($this->articleRepository->existsByCode($articleCode, $clinicId)) {
                    $skipped[] = 'article:' . $code;
                    continue;
                }

                $gtin = isset($articleData['gtin']) && \is_string($articleData['gtin']) ? $articleData['gtin'] : null;

                $this->commandBus->dispatch(new CreateNonDrugArticle(
                    clinicId: $command->clinicId,
                    name: isset($articleData['name']) && \is_string($articleData['name']) ? $articleData['name'] : '',
                    code: $code,
                    kind: isset($articleData['kind']) && \is_string($articleData['kind']) ? $articleData['kind'] : 'CONSUMABLE',
                    gtin: $gtin,
                    taxCategoryCode: isset($articleData['tax_category_code']) && \is_string($articleData['tax_category_code']) ? $articleData['tax_category_code'] : '',
                    basePriceMinorUnits: isset($articleData['base_price_minor_units']) && \is_int($articleData['base_price_minor_units']) ? $articleData['base_price_minor_units'] : 0,
                    basePriceCurrency: $currency,
                    unitOfMeasure: isset($articleData['unit_of_measure']) && \is_string($articleData['unit_of_measure']) ? $articleData['unit_of_measure'] : 'UNIT',
                    trackStock: !isset($articleData['track_stock']) || !\is_bool($articleData['track_stock']) || $articleData['track_stock'],
                ));

                $created[] = 'article:' . $code;
            } catch (\Throwable $e) {
                $this->logger->warning(\sprintf('Failed to create article "%s": %s', $code, $e->getMessage()));
                $failed[] = 'article:' . $code;
            }
        }

        // Create price lists
        foreach ($priceLists as $priceListData) {
            $name      = isset($priceListData['name']) && \is_string($priceListData['name']) ? $priceListData['name'] : 'Standard';
            $isDefault = isset($priceListData['is_default']) && \is_bool($priceListData['is_default']) && $priceListData['is_default'];

            try {
                if ($isDefault && $this->priceListRepository->hasDefaultForClinic($clinicId)) {
                    $skipped[] = 'price_list:' . $name;
                    continue;
                }

                $this->commandBus->dispatch(new CreatePriceList(
                    clinicId: $command->clinicId,
                    name: $name,
                    isDefault: $isDefault,
                ));

                $created[] = 'price_list:' . $name;
            } catch (\Throwable $e) {
                $this->logger->warning(\sprintf('Failed to create price list "%s": %s', $name, $e->getMessage()));
                $failed[] = 'price_list:' . $name;
            }
        }

        return new StarterCatalogApplicationReport(
            created: $created,
            skipped: $skipped,
            failed: $failed,
        );
    }
}
