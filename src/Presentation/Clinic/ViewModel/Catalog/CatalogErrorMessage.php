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
use App\Shared\Domain\UnitOfMeasure\Exception\UnknownUnitOfMeasureException;

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

            $exception instanceof UnknownUnitOfMeasureException => 'Cette unité de conditionnement n’est pas connue.',

            // Anything the domain raises without a name of its own is matched
            // on its message, so the page never shows an English sentence.
            default => self::plain($exception->getMessage()),
        };
    }

    private static function plain(string $message): string
    {
        $known = [
            'Invalid act code'          => 'Le code ne prend que des majuscules, chiffres, « _ » ou « - » (2 à 20 caractères).',
            'Invalid article code'      => 'Le code ne prend que des majuscules, chiffres, « _ » ou « - » (2 à 20 caractères).',
            'Invalid package code'      => 'Le code ne prend que des majuscules, chiffres, « _ » ou « - » (2 à 20 caractères).',
            'Invalid GTIN'              => 'Le code-barres compte 8 à 14 chiffres.',
            'Article name cannot be'    => 'Le libellé est obligatoire (150 caractères au plus).',
            'Package name cannot be'    => 'Le libellé est obligatoire (150 caractères au plus).',
            'Price list name cannot be' => 'Le nom de la grille est obligatoire.',
            'Act description cannot'    => 'La description ne dépasse pas 1 000 caractères.',
            'Act duration must be'      => 'La durée doit être d’au moins une minute.',
            'Quantity must be'          => 'La quantité doit être d’au moins 1.',
            'Unknown tax category'      => 'Cette famille comptable n’est pas connue.',
            'Invalid tax category'      => 'Cette famille comptable n’est pas connue.',
            'Act "'                     => 'Cet acte est introuvable.',
            'Article "'                 => 'Cet article est introuvable.',
            'Package "'                 => 'Ce forfait est introuvable.',
        ];

        foreach ($known as $prefix => $french) {
            if (str_starts_with($message, $prefix)) {
                return $french;
            }
        }

        return $message;
    }
}
