<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\UpdateClinicBillingSettings;

use App\Context\Clinic\Domain\Exception\InvalidBillingSettingsException;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\BillingSettings;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\VatRegime;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PostalAddress;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateClinicBillingSettingsHandler
{
    public function __construct(
        private readonly ClinicRepositoryInterface $clinicRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(UpdateClinicBillingSettings $command): void
    {
        $clinic = $this->clinicRepository->findById(ClinicId::fromString($command->clinicId));

        if (null === $clinic) {
            throw new \RuntimeException(\sprintf('Clinic with ID "%s" not found.', $command->clinicId));
        }

        $clinic->updateBillingSettings($this->settings($command), $this->clock->now());

        $this->clinicRepository->save($clinic);

        $this->domainEventPublisher->publish($clinic);
    }

    /**
     * A billing address needs a street, a city and a country to be one; anything
     * less means the clinic bills from its own address.
     */
    private function settings(UpdateClinicBillingSettings $command): BillingSettings
    {
        $street  = self::trimmed($command->streetLine1);
        $city    = self::trimmed($command->city);
        $country = self::trimmed($command->countryCode);

        $address = null !== $street && null !== $city && null !== $country
            ? PostalAddress::create(
                streetLine1: $street,
                city: $city,
                countryCode: $country,
                streetLine2: $command->streetLine2,
                postalCode: $command->postalCode,
            )
            : null;

        return BillingSettings::create(
            billingAddress: $address,
            invoiceNumberPrefix: $command->invoiceNumberPrefix,
            paymentTermsDays: $command->paymentTermsDays,
            iban: $command->iban,
            bic: $command->bic,
            invoiceFooterNote: $command->invoiceFooterNote,
            creditNotePrefix: $command->creditNotePrefix,
            professionalPaymentTermsDays: $command->professionalPaymentTermsDays,
            vatRegime: self::regime($command->vatRegime),
            vatOnDebits: $command->vatOnDebits,
            acceptedPaymentMethods: $command->acceptedPaymentMethods,
        );
    }

    private static function regime(?string $value): ?VatRegime
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return VatRegime::tryFrom($value) ?? throw InvalidBillingSettingsException::unknownVatRegime($value);
    }

    private static function trimmed(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = mb_trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
