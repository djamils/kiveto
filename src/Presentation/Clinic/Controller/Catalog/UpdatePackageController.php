<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Package\RevisePackage\RevisePackage;
use App\Context\Catalog\Application\Query\Package\GetPackageDetail\GetPackageDetail;
use App\Context\Catalog\Application\Query\Package\GetPackageDetail\PackageDetailView;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Presentation\Clinic\ViewModel\Catalog\PackageComposition;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Enregistrer » on a package's sheet — name, family, pricing and
 * composition — and the bulk bar's price revision of a fixed-price package.
 * Fields left out keep their value.
 */
#[Route(path: '/catalog/packages/{packageId}', name: 'clinic_catalogue_package_update', methods: ['POST'])]
final readonly class UpdatePackageController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private PackageComposition $composition,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request, string $packageId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $clinicId = $this->endpoint->clinicId();
        $form     = $request->request;

        try {
            $package = $this->queryBus->ask(new GetPackageDetail($packageId, $clinicId));
            \assert($package instanceof PackageDetailView);

            $mode      = $form->has('pricing_mode') ? $form->getString('pricing_mode') : $package->pricingMode;
            $price     = $form->has('price') ? CatalogEndpoint::cents($request, 'price') : $package->fixedPriceMinorUnits;
            $priceMode = $form->has('price') ? CatalogEndpoint::priceMode($request) : $package->fixedPriceMode;

            if ('FIXED_PRICE' === $mode && null === $price) {
                return $this->endpoint->refuse('Le prix forfaitaire saisi est invalide.');
            }

            if ('FIXED_PRICE' === $mode && null === $priceMode) {
                return $this->endpoint->refuse(CatalogEndpoint::INVALID_PRICE_MODE);
            }

            $this->commandBus->dispatch(new RevisePackage(
                packageId: $packageId,
                clinicId: $clinicId,
                name: CatalogEndpoint::text($request, 'name') ?? $package->name,
                taxCategoryCode: CatalogEndpoint::text($request, 'tax_category') ?? $package->taxCategoryCode,
                pricingMode: $mode,
                fixedPriceMinorUnits: 'FIXED_PRICE' === $mode ? $price : null,
                fixedPriceCurrency: 'FIXED_PRICE' === $mode ? 'EUR' : null,
                fixedPriceMode: 'FIXED_PRICE' === $mode ? $priceMode : null,
            ));

            $wanted = PackageComposition::wanted($request);

            if (null !== $wanted) {
                $this->composition->sync($packageId, $clinicId, $package->components, $wanted);
            }
        } catch (\ValueError $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
