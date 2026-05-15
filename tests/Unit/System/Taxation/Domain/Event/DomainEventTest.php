<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\Event;

use App\System\Taxation\Domain\Event\TaxCategoryRegistered;
use App\System\Taxation\Domain\Event\TaxRateAdded;
use App\System\Taxation\Domain\Event\TaxRegimeActivated;
use App\System\Taxation\Domain\Event\TaxRegimeDeactivated;
use App\System\Taxation\Domain\Event\TaxRegimeLoaded;
use PHPUnit\Framework\TestCase;

final class DomainEventTest extends TestCase
{
    public function testTaxCategoryRegisteredAggregateId(): void
    {
        $event = new TaxCategoryRegistered('veterinary.act.consultation', 'Consultation vétérinaire');

        self::assertSame('veterinary.act.consultation', $event->aggregateId());
    }

    public function testTaxCategoryRegisteredPayload(): void
    {
        $event = new TaxCategoryRegistered('veterinary.act.consultation', 'Consultation vétérinaire');

        self::assertSame([
            'code'        => 'veterinary.act.consultation',
            'displayName' => 'Consultation vétérinaire',
        ], $event->payload());
    }

    public function testTaxRateAddedAggregateId(): void
    {
        $event = new TaxRateAdded('FR', 'fr.standard', 2000);

        self::assertSame('FR', $event->aggregateId());
    }

    public function testTaxRateAddedPayload(): void
    {
        $event = new TaxRateAdded('FR', 'fr.standard', 2000);

        self::assertSame([
            'regimeId'         => 'FR',
            'rateId'           => 'fr.standard',
            'valueBasisPoints' => 2000,
        ], $event->payload());
    }

    public function testTaxRegimeLoadedAggregateId(): void
    {
        $event = new TaxRegimeLoaded('FR', 'Régime TVA France', 'FR');

        self::assertSame('FR', $event->aggregateId());
    }

    public function testTaxRegimeLoadedPayload(): void
    {
        $event = new TaxRegimeLoaded('FR', 'Régime TVA France', 'FR');

        self::assertSame([
            'regimeId' => 'FR',
            'name'     => 'Régime TVA France',
            'country'  => 'FR',
        ], $event->payload());
    }

    public function testTaxRegimeActivatedAggregateId(): void
    {
        $event = new TaxRegimeActivated('FR');

        self::assertSame('FR', $event->aggregateId());
    }

    public function testTaxRegimeActivatedPayload(): void
    {
        $event = new TaxRegimeActivated('FR');

        self::assertSame(['regimeId' => 'FR'], $event->payload());
    }

    public function testTaxRegimeDeactivatedAggregateId(): void
    {
        $event = new TaxRegimeDeactivated('CH');

        self::assertSame('CH', $event->aggregateId());
    }

    public function testTaxRegimeDeactivatedPayload(): void
    {
        $event = new TaxRegimeDeactivated('CH');

        self::assertSame(['regimeId' => 'CH'], $event->payload());
    }
}
