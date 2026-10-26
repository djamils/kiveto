<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Catalog;

use App\Context\Catalog\Application\Command\Package\CreatePackage\CreatePackage;
use App\Presentation\Clinic\ViewModel\Catalog\CatalogEndpoint;
use App\Presentation\Clinic\ViewModel\Catalog\PackageComposition;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** « Ajouter au catalogue » on a new package's sheet, with its composition. */
#[Route(path: '/catalog/packages', name: 'clinic_catalogue_package_create', methods: ['POST'])]
final readonly class CreatePackageController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private PackageComposition $composition,
        private CatalogEndpoint $endpoint,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $mode      = $request->request->getString('pricing_mode', 'SUM_OF_COMPONENTS');
        $price     = 'FIXED_PRICE' === $mode ? CatalogEndpoint::cents($request, 'price') : null;
        $priceMode = 'FIXED_PRICE' === $mode ? CatalogEndpoint::priceMode($request) : null;

        if ('FIXED_PRICE' === $mode && null === $price) {
            return $this->endpoint->refuse('Le prix forfaitaire saisi est invalide.');
        }

        if ('FIXED_PRICE' === $mode && null === $priceMode) {
            return $this->endpoint->refuse(CatalogEndpoint::INVALID_PRICE_MODE);
        }

        $clinicId = $this->endpoint->clinicId();

        try {
            $id = $this->commandBus->dispatch(new CreatePackage(
                clinicId: $clinicId,
                name: mb_trim($request->request->getString('name')),
                code: mb_strtoupper(mb_trim($request->request->getString('code'))),
                taxCategoryCode: $request->request->getString('tax_category'),
                pricingMode: $mode,
                fixedPriceMinorUnits: $price,
                fixedPriceCurrency: null !== $price ? 'EUR' : null,
                fixedPriceMode: $priceMode,
            ));
            \assert(\is_string($id));

            $this->composition->sync($id, $clinicId, [], PackageComposition::wanted($request) ?? []);
        } catch (\ValueError $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok(['id' => $id]);
    }
}
