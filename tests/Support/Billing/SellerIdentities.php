<?php

declare(strict_types=1);

namespace App\Tests\Support\Billing;

use App\Context\Billing\Domain\ValueObject\SellerIdentity;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;

/**
 * Sellers for Billing tests.
 */
final class SellerIdentities
{
    /**
     * A clinic whose letterhead holds everything an electronic invoice needs.
     */
    public static function complete(?int $professionalPaymentTermsDays = 30): SellerIdentity
    {
        $siren = Siren::fromInput('812456903');

        return new SellerIdentity(
            legalName: 'SELARL Clinique Vétérinaire du Parc',
            siren: $siren,
            vatNumber: FrenchVatNumber::forSiren($siren),
            streetLines: ['12 rue de la Paix'],
            postalCode: '75002',
            city: 'Paris',
            countryCode: 'FR',
            legalSentence: 'SELARL au capital de 10 000 € — RCS Paris 812 456 903',
            professionalPaymentTermsDays: $professionalPaymentTermsDays,
            iban: 'FR7630006000011234567890189',
            bic: 'AGRIFRPP',
            numberingIsUnique: true,
        );
    }

    /**
     * A clinic the Clinic context knows nothing about.
     */
    public static function unknown(): SellerIdentity
    {
        return new SellerIdentity('', null, null, [], null, null, 'FR', null, null, null, null, true);
    }
}
