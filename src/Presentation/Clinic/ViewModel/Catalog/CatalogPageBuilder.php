<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Catalog;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ActListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ArticleListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListActs;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListArticles;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPackageComponents;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPackages;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPriceListEntries;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PackageComponentRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PackageListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PriceListEntryRow;
use App\Context\Catalog\Application\Query\Pricing\ListPriceLists\ListPriceLists;
use App\Context\Catalog\Application\Query\Pricing\ListPriceLists\PriceListCollection;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Inventory\Application\Query\ListStockItemsByClinic\ListStockItemsByClinic;
use App\Context\Inventory\Application\Query\ListStockItemsByClinic\StockItemSummaryView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Application\Query\GetEffectiveRateForUI\EffectiveRateResult;
use App\System\Taxation\Application\Query\GetEffectiveRateForUI\GetEffectiveRateForUI;
use App\System\Taxation\Application\Query\ListTaxCategories\ListTaxCategories;
use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Everything the « Catalogue » page module starts from (catalogue-v-topbar_1):
 * the clinic's acts, articles, packages and price lists, archived ones
 * included, with the accounting families and their VAT rate so the page can
 * show prices all taxes included.
 *
 * A clinic's catalogue is a few hundred lines at most, so it travels whole:
 * the page searches, filters, sorts and pages it in the browser, like the
 * mock-up, and asks clinic_catalogue_data again after every change.
 *
 * Prices travel as the clinic entered them: `price` (minor units) and `pm`,
 * its mode ('incl' all taxes included, 'excl' excluding tax). The page posts
 * back exactly what it shows; the other figure is a preview from the
 * family's rate, never stored.
 */
final readonly class CatalogPageBuilder
{
    public const string CSRF_ID = 'catalog';

    public const string NIL = '00000000-0000-7000-8000-000000000000';

    private const int WHOLE = 5000;

    private const array KINDS = ['DRUG' => 'med', 'CONSUMABLE' => 'conso', 'FOOD' => 'ali', 'EQUIPMENT' => 'mat'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $clinicId, string $tab): array
    {
        return [
            'tab'   => $tab,
            'token' => $this->csrf->getToken(self::CSRF_ID)->getValue(),
            'urls'  => $this->urls(),
        ] + $this->data($clinicId);
    }

    /**
     * The catalogue itself — what the page asks again after a change.
     *
     * @return array{fam: list<array{v: string, l: string, t: float}>, acts: list<array<string, mixed>>, articles: list<array<string, mixed>>, forfaits: list<array<string, mixed>>, grilles: list<array<string, mixed>>}
     */
    public function data(string $clinicId): array
    {
        return [
            'fam'      => $this->families($clinicId),
            'acts'     => $this->acts($clinicId),
            'articles' => $this->articles($clinicId),
            'forfaits' => $this->packages($clinicId),
            'grilles'  => $this->priceLists($clinicId),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        $nil = self::NIL;

        return [
            'data'         => $this->urls->generate('clinic_catalogue_data'),
            'export'       => $this->urls->generate('clinic_catalogue_export'),
            'registry'     => $this->urls->generate('clinic_catalogue_registry_search'),
            'registryItem' => $this->urls->generate('clinic_catalogue_registry_detail', ['authorizationId' => $nil]),
            'stock'        => $this->urls->generate('clinic_stock'),
            'tabActes'     => $this->urls->generate('clinic_catalogue'),
            'tabArticles'  => $this->urls->generate('clinic_catalogue_articles'),
            'tabForfaits'  => $this->urls->generate('clinic_catalogue_packages'),
            'tabGrilles'   => $this->urls->generate('clinic_catalogue_price_lists'),
            'actCreate'    => $this->urls->generate('clinic_catalogue_act_create'),
            'actUpdate'    => $this->urls->generate('clinic_catalogue_act_update', ['actId' => $nil]),
            'actArchive'   => $this->urls->generate('clinic_catalogue_act_archive', ['actId' => $nil]),
            'actRestore'   => $this->urls->generate('clinic_catalogue_act_restore', ['actId' => $nil]),
            'artCreate'    => $this->urls->generate('clinic_catalogue_article_create'),
            'artUpdate'    => $this->urls->generate('clinic_catalogue_article_update', ['articleId' => $nil]),
            'artArchive'   => $this->urls->generate('clinic_catalogue_article_archive', ['articleId' => $nil]),
            'artRestore'   => $this->urls->generate('clinic_catalogue_article_restore', ['articleId' => $nil]),
            'pkgCreate'    => $this->urls->generate('clinic_catalogue_package_create'),
            'pkgUpdate'    => $this->urls->generate('clinic_catalogue_package_update', ['packageId' => $nil]),
            'pkgArchive'   => $this->urls->generate('clinic_catalogue_package_archive', ['packageId' => $nil]),
            'pkgRestore'   => $this->urls->generate('clinic_catalogue_package_restore', ['packageId' => $nil]),
            'gridCreate'   => $this->urls->generate('clinic_catalogue_price_list_create'),
            'gridItemSave' => $this->urls->generate('clinic_catalogue_price_item_save', ['priceListId' => $nil]),
            'gridItemDrop' => $this->urls->generate('clinic_catalogue_price_item_remove', ['priceListId' => $nil, 'entryId' => $nil]),
        ];
    }

    /**
     * The accounting families (tax categories) and the rate the clinic's tax
     * regime applies to each today.
     *
     * @return list<array{v: string, l: string, t: float}>
     */
    private function families(string $clinicId): array
    {
        $categories = $this->queryBus->ask(new ListTaxCategories());

        if (!\is_array($categories)) {
            return [];
        }

        $clinic = $this->clinic($clinicId);
        $out    = [];

        foreach ($categories as $category) {
            if (!$category instanceof TaxCategory) {
                continue;
            }

            $out[] = [
                'v' => $category->code()->toString(),
                'l' => $category->displayName(),
                't' => null !== $clinic ? $this->rate($category->code(), $clinic) : 0.0,
            ];
        }

        return $out;
    }

    private function rate(TaxCategoryCode $code, ClinicDto $clinic): float
    {
        $country = mb_strtoupper($clinic->countryCode);
        $regime  = null === $clinic->jurisdictionCode || '' === $clinic->jurisdictionCode
            ? $country
            : $country . '-' . mb_strtoupper($clinic->jurisdictionCode);

        try {
            $result = $this->queryBus->ask(new GetEffectiveRateForUI(
                categoryCode: $code,
                regimeId: TaxRegimeId::fromString($regime),
                fiscalContext: FiscalContext::minimal(CountryCode::fromString($clinic->countryCode), $this->clock->now()),
            ));
        } catch (\Throwable) {
            return 0.0;
        }

        return $result instanceof EffectiveRateResult ? (float) $result->ratePercent : 0.0;
    }

    private function clinic(string $clinicId): ?ClinicDto
    {
        try {
            $clinic = $this->queryBus->ask(new GetClinic($clinicId));
        } catch (\Throwable) {
            return null;
        }

        return $clinic instanceof ClinicDto ? $clinic : null;
    }

    private function everything(): CatalogListCriteria
    {
        return new CatalogListCriteria(status: null, perPage: self::WHOLE);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function acts(string $clinicId): array
    {
        $page = $this->queryBus->ask(new ListActs($clinicId, $this->everything()));

        if (!$page instanceof CatalogPage) {
            return [];
        }

        $out = [];

        foreach ($page->items as $act) {
            if (!$act instanceof ActListRow) {
                continue;
            }

            $out[] = [
                'id'    => $act->id,
                'k'     => 'acte',
                'code'  => $act->code,
                'lib'   => $act->name,
                'desc'  => $act->description ?? '',
                'cat'   => $act->category,
                'dur'   => $act->estimatedDurationMinutes,
                'ag'    => $act->requiresAnesthesia,
                'fam'   => $act->taxCategoryCode,
                'price' => $act->basePriceMinorUnits,
                'pm'    => $act->basePriceMode,
                'arch'  => $act->isArchived(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(string $clinicId): array
    {
        $page = $this->queryBus->ask(new ListArticles($clinicId, $this->everything()));

        if (!$page instanceof CatalogPage) {
            return [];
        }

        $stock = $this->stock($clinicId);
        $out   = [];

        foreach ($page->items as $article) {
            if (!$article instanceof ArticleListRow) {
                continue;
            }

            $out[] = [
                'id'     => $article->id,
                'k'      => 'article',
                'code'   => $article->code,
                'lib'    => $article->name,
                'type'   => self::KINDS[$article->kind] ?? 'conso',
                'fam'    => $article->taxCategoryCode,
                'price'  => $article->basePriceMinorUnits,
                'pm'     => $article->basePriceMode,
                'gtin'   => $article->gtin ?? '',
                'uom'    => $article->unitOfMeasure,
                'suivi'  => $article->trackStock,
                'classe' => $article->isDrug() ? ($article->prescriptionClass ?? 'NONE') : 'NONE',
                'auth'   => $article->authorizationRef,
                'arch'   => $article->isArchived(),
                'stock'  => $stock[$article->id] ?? null,
            ];
        }

        return $out;
    }

    /**
     * What Inventory holds of each article: on hand, alert threshold, lots.
     *
     * @return array<string, array{q: float, seuil: float, lots: int}>
     */
    private function stock(string $clinicId): array
    {
        try {
            $items = $this->queryBus->ask(new ListStockItemsByClinic($clinicId, null, self::WHOLE));
        } catch (\Throwable) {
            return [];
        }

        if (!\is_array($items)) {
            return [];
        }

        $out = [];

        foreach ($items as $item) {
            if (!$item instanceof StockItemSummaryView) {
                continue;
            }

            $out[$item->articleId] = [
                'q'     => (float) $item->totalOnHandAmount,
                'seuil' => (float) $item->thresholdAmount,
                'lots'  => $item->activeLotCount,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packages(string $clinicId): array
    {
        $page = $this->queryBus->ask(new ListPackages($clinicId, $this->everything()));

        if (!$page instanceof CatalogPage) {
            return [];
        }

        $out = [];

        foreach ($page->items as $package) {
            if (!$package instanceof PackageListRow) {
                continue;
            }

            $rows = $this->queryBus->ask(new ListPackageComponents($clinicId, $package->id));
            $comp = [];

            foreach (\is_array($rows) ? $rows : [] as $row) {
                if ($row instanceof PackageComponentRow) {
                    $comp[] = [
                        'cid'  => $row->id,
                        'type' => $row->itemType,
                        'item' => $row->itemId,
                        'code' => $row->itemCode ?? '',
                        'lib'  => $row->itemName ?? '',
                        'q'    => $row->quantity,
                    ];
                }
            }

            $out[] = [
                'id'    => $package->id,
                'k'     => 'forfait',
                'code'  => $package->code,
                'lib'   => $package->name,
                'fam'   => $package->taxCategoryCode,
                'mode'  => $package->isFixedPrice() ? 'fixe' : 'somme',
                'price' => $package->fixedPriceMinorUnits,
                'pm'    => $package->fixedPriceMode,
                'arch'  => $package->isArchived(),
                'comp'  => $comp,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function priceLists(string $clinicId): array
    {
        $collection = $this->queryBus->ask(new ListPriceLists($clinicId));

        if (!$collection instanceof PriceListCollection) {
            return [];
        }

        $out = [];

        foreach ($collection->items as $list) {
            $rows   = $this->queryBus->ask(new ListPriceListEntries($clinicId, $list->id));
            $tarifs = [];

            foreach (\is_array($rows) ? $rows : [] as $row) {
                if ($row instanceof PriceListEntryRow) {
                    $tarifs[] = [
                        'eid'   => $row->id,
                        'type'  => $row->itemType,
                        'item'  => $row->itemId,
                        'code'  => $row->itemCode ?? '',
                        'lib'   => $row->itemName ?? '',
                        'price' => $row->priceMinorUnits,
                        'pm'    => $row->priceMode,
                        'fam'   => $row->taxCategoryOverride,
                    ];
                }
            }

            $out[] = [
                'id'     => $list->id,
                'lib'    => $list->name,
                'defaut' => $list->isDefault,
                'tarifs' => $tarifs,
            ];
        }

        // The default list is the one the clinic charges from: it comes first.
        usort($out, static fn (array $a, array $b): int => (int) $b['defaut'] <=> (int) $a['defaut']);

        return $out;
    }
}
