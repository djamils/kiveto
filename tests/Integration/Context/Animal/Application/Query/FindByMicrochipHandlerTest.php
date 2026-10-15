<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Application\Query;

use App\Context\Animal\Application\Query\FindByMicrochip\AnimalMicrochipView;
use App\Context\Animal\Application\Query\FindByMicrochip\FindByMicrochip;
use App\Context\Animal\Application\Query\FindByMicrochip\FindByMicrochipHandler;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * « Lire la puce » at the desk: the chip finds the clinic's own file, typed
 * with or without the reader's spaces, and never another clinic's.
 */
final class FindByMicrochipHandlerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '01960000-0000-7000-8000-0000000000d1';
    private const string OTHER_CLINIC_ID = '01960000-0000-7000-8000-0000000000d2';
    private const string ULYSSE          = '01960000-0000-7000-8000-0000000000d3';
    private const string CHIP            = '250269812340011';

    private FindByMicrochipHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(FindByMicrochipHandler::class);
        \assert($handler instanceof FindByMicrochipHandler);
        $this->handler = $handler;

        AnimalEntityFactory::new()->withId(self::ULYSSE)->withClinicId(self::CLINIC_ID)->withName('Ulysse')->withMicrochip(self::CHIP)->create();
        AnimalEntityFactory::new()->withClinicId(self::OTHER_CLINIC_ID)->withName('Ailleurs')->withMicrochip('250269812340099')->create();
    }

    public function testTheChipFindsTheClinicsFile(): void
    {
        $view = ($this->handler)(new FindByMicrochip(self::CLINIC_ID, self::CHIP));

        self::assertInstanceOf(AnimalMicrochipView::class, $view);
        self::assertSame(self::ULYSSE, $view->animalId);
        self::assertSame(self::CLINIC_ID, $view->clinicId);
        self::assertSame('Ulysse', $view->name);
    }

    public function testTheReadersSpacesAreIgnored(): void
    {
        $view = ($this->handler)(new FindByMicrochip(self::CLINIC_ID, '250 269 812 340 011'));

        self::assertSame(self::ULYSSE, $view?->animalId);
    }

    public function testAnotherClinicsChipIsUnknownHere(): void
    {
        self::assertNull(($this->handler)(new FindByMicrochip(self::CLINIC_ID, '250269812340099')));
    }

    public function testNoDigitsMeansNoLookup(): void
    {
        self::assertNull(($this->handler)(new FindByMicrochip(self::CLINIC_ID, '—')));
    }
}
