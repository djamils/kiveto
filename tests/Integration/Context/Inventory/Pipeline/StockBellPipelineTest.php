<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Inventory\Pipeline;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Inventory\Application\Command\ConsumeStock\ConsumeStock;
use App\Context\Inventory\Application\Command\NotifyExpiringLots\NotifyExpiringLots;
use App\Context\Inventory\Application\Command\OpenStockItem\OpenStockItem;
use App\Context\Inventory\Application\Command\ReceiveStock\ReceiveStock;
use App\Fixtures\Context\Catalog\Factory\ArticleEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Inventory\Factory\LotEntityFactory;
use App\Fixtures\Context\Inventory\Factory\StockItemEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\Notification\Application\Query\ListInAppNotifications\InAppNotificationView;
use App\System\Notification\Application\Query\ListInAppNotifications\ListInAppNotifications;
use App\Tests\Support\MessengerDrain;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 39 through the real wiring: a stock-out raised by a stock change is
 * announced to the assistants by the `async` worker; the full re-projection
 * announces nothing; the expiry scan announces the lots once, to the
 * administrators of a clinic without assistant.
 */
final class StockBellPipelineTest extends KernelTestCase
{
    use Factories;

    private string $clinicId;
    private string $assistant;
    private string $administrator;

    protected function setUp(): void
    {
        $this->clinicId      = Uuid::v7()->toRfc4122();
        $this->assistant     = Uuid::v7()->toRfc4122();
        $this->administrator = Uuid::v7()->toRfc4122();

        $this->member($this->administrator, ClinicMemberRole::MANAGER, administrator: true);
    }

    public function testAStockOutIsAnnouncedToTheAssistants(): void
    {
        $this->member($this->assistant, ClinicMemberRole::VETERINARY_ASSISTANT);
        $invited = Uuid::v7()->toRfc4122();
        $this->member($invited, ClinicMemberRole::VETERINARY_ASSISTANT, account: UserStatus::PENDING);
        $articleId = Uuid::v7();
        ArticleEntityFactory::new()->forClinic($this->clinicId)->create(['id' => $articleId, 'code' => 'VERM', 'name' => 'Vermifuge Milbemax', 'trackStock' => true]);

        $itemId = $this->commandBus()->dispatch(new OpenStockItem(clinicId: $this->clinicId, articleId: $articleId->toRfc4122(), unit: 'UNIT', thresholdAmount: '2.000000', thresholdUnit: 'UNIT', thresholdType: 'ABSOLUTE', trackStock: true));
        \assert(\is_string($itemId));
        $this->commandBus()->dispatch(new ReceiveStock(stockItemId: $itemId, clinicId: $this->clinicId, lotNumber: 'LOT-A', quantityAmount: '3.000000', quantityUnit: 'UNIT', expiryDate: new \DateTimeImmutable('+200 days')->format('Y-m-d'), sourceSupplierId: null, occurredAt: null, reference: null, performedBy: null));
        $this->commandBus()->dispatch(new ConsumeStock(stockItemId: $itemId, clinicId: $this->clinicId, quantityAmount: '3.000000', quantityUnit: 'UNIT', occurredAt: null, reference: null, performedBy: null, note: null, reason: 'CONSUMPTION_CONSULTATION'));

        MessengerDrain::drain(self::getContainer(), ['async']);

        $entries = $this->bell($this->assistant);
        self::assertCount(1, $entries);
        self::assertSame(['CRITICAL_STOCK', 'Stock critique · Vermifuge Milbemax', 'Rupture de stock', false], [$entries[0]->kind, $entries[0]->title, $entries[0]->detail, $entries[0]->read]);
        self::assertStringEndsWith('/stock?article=' . $articleId->toRfc4122(), $this->url($entries[0]));
        self::assertSame([], $this->bell($this->administrator));
        self::assertSame([], $this->bell($invited), 'an invited assistant who never activated is not told');
    }

    public function testTheFullReprojectionAnnouncesNothing(): void
    {
        $this->member($this->assistant, ClinicMemberRole::VETERINARY_ASSISTANT);
        StockItemEntityFactory::new()->withClinicId($this->clinicId)->withArticleId(Uuid::v7()->toRfc4122())->withTotalOnHand('0.000000', 'UNIT')->create();

        $tester = new CommandTester(new Application(self::bootKernel())->find('app:inventory:detect-alerts'));
        self::assertSame(0, $tester->execute(['--clinic' => $this->clinicId]));

        MessengerDrain::drain(self::getContainer(), ['async']);
        self::assertSame([], $this->bell($this->assistant));
    }

    public function testExpiringLotsAreAnnouncedOnceToTheAdministratorsOfAClinicWithoutAssistant(): void
    {
        $item = StockItemEntityFactory::new()->withClinicId($this->clinicId)->withArticleId(Uuid::v7()->toRfc4122())->create()->_real();
        LotEntityFactory::new()->expiringIn(10)->create(['stockItem' => $item]);
        LotEntityFactory::new()->expiringIn(20)->create(['stockItem' => $item]);

        $this->commandBus()->dispatch(new NotifyExpiringLots());
        $this->commandBus()->dispatch(new NotifyExpiringLots());

        $entries = $this->bell($this->administrator);
        self::assertCount(1, $entries);
        self::assertSame(['EXPIRING_LOTS', 'Péremption proche · 2 lots', 'Expirent dans moins de 30 jours · voir Stocks'], [$entries[0]->kind, $entries[0]->title, $entries[0]->detail]);
        self::assertStringEndsWith('/stock?onglet=alertes', $this->url($entries[0]));
    }

    private function member(string $userId, ClinicMemberRole $role, bool $administrator = false, UserStatus $account = UserStatus::ACTIVE): void
    {
        ClinicUserFactory::createOne(['id' => Uuid::fromString($userId), 'status' => $account]);
        $factory = ClinicMembershipEntityFactory::new()->withClinicId($this->clinicId)->withUserId($userId)->withRole($role)->asEmployee();
        ($administrator ? $factory->asClinicAdministrator() : $factory)->create();
    }

    /**
     * @return list<InAppNotificationView>
     */
    private function bell(string $userId): array
    {
        $queryBus = self::getContainer()->get(QueryBusInterface::class);
        \assert($queryBus instanceof QueryBusInterface);
        $entries = $queryBus->ask(new ListInAppNotifications($userId, $this->clinicId));
        \assert(\is_array($entries));

        return array_values(array_filter($entries, static fn (mixed $e): bool => $e instanceof InAppNotificationView));
    }

    private function url(InAppNotificationView $entry): string
    {
        $router = self::getContainer()->get(UrlGeneratorInterface::class);
        \assert($router instanceof UrlGeneratorInterface);

        return $router->generate($entry->route, $entry->routeParams);
    }

    private function commandBus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }
}
