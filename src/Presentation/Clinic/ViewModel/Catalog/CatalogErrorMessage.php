<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Catalog;

use App\Context\Catalog\Domain\Act\Exception\ArchivedActCannotBeModifiedException;
use App\Context\Catalog\Domain\Act\Exception\DuplicateActCodeException;
use App\Context\Catalog\Domain\Act\Exception\InvalidActNameException;
use App\Context\Catalog\Domain\Article\Exception\ArchivedArticleCannotBeModifiedException;
use App\Context\Catalog\Domain\Article\Exception\AuthorizationNotMarketableException;
use App\Context\Catalog\Domain\Article\Exception\DuplicateArticleCodeException;
use App\Context\Catalog\Domain\Article\Exception\DuplicateGtinException;
use App\Context\Catalog\Domain\Article\Exception\InvalidDrugPropertiesException;
use App\Context\Catalog\Domain\Article\Exception\RegulatoryRestoreForbiddenException;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Context\Catalog\Domain\Package\Exception\ArchivedItemInPackageException;
use App\Context\Catalog\Domain\Package\Exception\DuplicatePackageCodeException;
use App\Context\Catalog\Domain\Package\Exception\InvalidPackagePricingModeException;
use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;

/**
 * Says in French what the Catalog domain refused.
 *
 * The bounded context speaks English to its developers; the counter speaks
 * French to its users. Mapping the exception type rather than rewording the
 * domain keeps the invariant in one place and the sentence in the other.
 */
final class CatalogErrorMessage
{
    public static function of(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof DuplicateActCodeException,
            $exception instanceof DuplicateArticleCodeException,
            $exception instanceof DuplicatePackageCodeException => 'Ce code est déjà utilisé dans le catalogue de la clinique.',

            $exception instanceof DuplicateGtinException => 'Ce code-barres est déjà porté par un autre article.',

            $exception instanceof ArchivedActCannotBeModifiedException,
            $exception instanceof ArchivedArticleCannotBeModifiedException => 'Cet élément est archivé : remettez-le au catalogue avant de le modifier.',

            $exception instanceof ArchivedItemInPackageException => 'Cette prestation est archivée et ne peut pas entrer dans un forfait.',

            $exception instanceof RegulatoryRestoreForbiddenException,
            $exception instanceof AuthorizationNotMarketableException => 'L’autorisation de mise sur le marché de ce médicament a été retirée : il ne peut plus être remis au catalogue.',

            $exception instanceof InvalidDrugPropertiesException => 'Les mentions de délivrance ne s’appliquent qu’aux médicaments.',

            $exception instanceof InvalidPackagePricingModeException => 'Un forfait au tarif forfaitaire doit porter un tarif, et un forfait à la somme des prestations n’en porte aucun.',

            $exception instanceof InvalidActNameException => 'Le libellé est obligatoire.',

            $exception instanceof ClinicCurrencyMismatchException => 'Ce tarif n’est pas dans la devise de la clinique.',

            $exception instanceof PriceListNotFoundException => 'Cette grille tarifaire est introuvable.',

            // Anything the domain raises without a name of its own — the tax
            // category registry, mostly — still says what it refused.
            default => $exception->getMessage(),
        };
    }
}
