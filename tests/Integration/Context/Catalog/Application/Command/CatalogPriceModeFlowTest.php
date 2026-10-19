<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Catalog\Application\Command;

use App\Context\Catalog\Application\Command\Act\ChangeActBasePrice\ChangeActBasePrice;
use App\Context\Catalog\Application\Command\Act\CreateAct\CreateAct;
use App\Context\Catalog\Application\Query\Act\GetActDetail\ActDetailView;
use App\Context\Catalog\Application\Query\Act\GetActDetail\GetActDetail;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ActListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListActs;
use App\Context\Catalog\Domain\Exception\InvalidPriceEntryModeException;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\System\Taxation\Factory\TaxCategoryEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * A catalogue price goes in and comes out exactly as the clinic typed it:
 * amount and mode, never converted (AC 6).
 */
final class CatalogPriceModeFlowTest extends KernelTestCase
{
    use Factories;

    private CommandBusInterface $commandBus;
    private QueryBusInterface $queryBus;
    private string $clinicId;

    protected function setUp(): void
    {
        $container = static::getContainer();

        $commandBus = $container->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $this->commandBus = $commandBus;

        $queryBus = $container->get(QueryBusInterface::class);
        \assert($queryBus instanceof QueryBusInterface);
        $this->queryBus = $queryBus;

        $this->clinicId = Uuid::v7()->toRfc4122();
        ClinicEntityFactory::createOne(['id' => Uuid::fromString($this->clinicId)]);
        TaxCategoryEntityFactory::createOne(['code' => 'veterinary.act.consultation']);
    }

    public function testAnActSavedAtFortyFiveEurosTtcIsReloadedAsEntered(): void
    {
        $actId = $this->createAct(4500, 'incl');

        $detail = $this->queryBus->ask(new GetActDetail($actId, $this->clinicId));
        \assert($detail instanceof ActDetailView);

        self::assertSame(4500, $detail->basePriceMinorUnits);
        self::assertSame('EUR', $detail->basePriceCurrency);
        self::assertSame('incl', $detail->basePriceMode);

        $page = $this->queryBus->ask(new ListActs($this->clinicId, new CatalogListCriteria()));
        \assert($page instanceof CatalogPage);
        $row = $page->items[0];
        \assert($row instanceof ActListRow);

        self::assertSame(4500, $row->basePriceMinorUnits);
        self::assertSame('incl', $row->basePriceMode);
    }

    public function testAPriceReEnteredExcludingTaxKeepsItsAmountAndTakesItsMode(): void
    {
        $actId = $this->createAct(4500, 'incl');

        $this->commandBus->dispatch(new ChangeActBasePrice($actId, $this->clinicId, 3750, 'EUR', 'excl'));

        $detail = $this->queryBus->ask(new GetActDetail($actId, $this->clinicId));
        \assert($detail instanceof ActDetailView);

        self::assertSame(3750, $detail->basePriceMinorUnits);
        self::assertSame('excl', $detail->basePriceMode);
    }

    public function testAnUnknownModeIsRefused(): void
    {
        $this->expectException(InvalidPriceEntryModeException::class);

        $this->createAct(4500, 'ttc');
    }

    private function createAct(int $price, string $mode): string
    {
        $id = $this->commandBus->dispatch(new CreateAct(
            clinicId: $this->clinicId,
            name: 'Consultation standard',
            code: 'CONS_STD',
            description: null,
            category: 'CONSULTATION',
            taxCategoryCode: 'veterinary.act.consultation',
            basePriceMinorUnits: $price,
            basePriceCurrency: 'EUR',
            basePriceMode: $mode,
            estimatedDurationMinutes: 20,
            requiresAnesthesia: false,
        ));
        \assert(\is_string($id));

        return $id;
    }
}
