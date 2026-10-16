<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordCounterSale;

/**
 * A newcomer the desk sells to, as typed in the « Nouveau particulier » form:
 * their record is created with the sale. The phone is the national number
 * (ten digits); either of the phone and the e-mail may be empty.
 */
final readonly class CounterSaleNewClient
{
    public const string MISTER = 'mister';
    public const string MADAM  = 'madam';

    public function __construct(
        public string $civility,
        public string $lastName,
        public string $firstName,
        public string $phone = '',
        public string $email = '',
    ) {
    }

    /** « 0612345678 » → « +33612345678 »; empty when there is no phone. */
    public function internationalPhone(): string
    {
        return '' === $this->phone ? '' : '+33' . substr($this->phone, 1);
    }

    /** What the invoice is made out to: « Nadia BENALI ». */
    public function displayName(): string
    {
        return mb_trim($this->firstName . ' ' . $this->lastName);
    }
}
