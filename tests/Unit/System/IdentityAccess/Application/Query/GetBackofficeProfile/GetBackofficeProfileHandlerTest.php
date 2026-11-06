<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\GetBackofficeProfile;

use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\GetBackofficeProfile;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\GetBackofficeProfileHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GetBackofficeProfileHandlerTest extends TestCase
{
    private const string ADMIN = '01960000-0000-7000-8000-000000005a01';

    public function testAnswersTheProfileOfTheAccount(): void
    {
        $view     = new BackofficeProfileView(self::ADMIN, 'admin@kiveto.fr', 'Admin', 'Europe/Paris', true, false, new \DateTimeImmutable('2026-01-15 09:30:00'));
        $profiles = $this->createMock(BackofficeProfileStoreInterface::class);
        $profiles->expects(self::once())->method('find')->with(self::ADMIN)->willReturn($view);

        self::assertSame($view, new GetBackofficeProfileHandler($profiles)(new GetBackofficeProfile(self::ADMIN)));
    }

    public function testAnswersNullForAnIdThatIsNoBackOfficeAccount(): void
    {
        $profiles = $this->createMock(BackofficeProfileStoreInterface::class);
        $profiles->expects(self::once())->method('find')->willReturn(null);

        self::assertNull(new GetBackofficeProfileHandler($profiles)(new GetBackofficeProfile(self::ADMIN)));
    }

    #[DataProvider('provideAnAccountThatNeverChoseANameSignsWithItsAddressCases')]
    public function testAnAccountThatNeverChoseANameSignsWithItsAddress(string $email, string $name): void
    {
        self::assertSame($name, BackofficeProfileView::nameFromEmail($email));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAnAccountThatNeverChoseANameSignsWithItsAddressCases(): iterable
    {
        yield 'first and last name' => ['djamil.soidiki@kiveto.fr', 'Djamil Soidiki'];

        yield 'every separator' => ['jean-pierre_martin+console@kiveto.fr', 'Jean Pierre Martin Console'];

        yield 'capitals and accents' => ['ÉLODIE.durand@kiveto.fr', 'Élodie Durand'];

        yield 'repeated separators' => ['.marie..curie.@kiveto.fr', 'Marie Curie'];

        yield 'no at sign' => ['admin', 'Admin'];

        yield 'only separators' => ['._-@kiveto.fr', '._-@kiveto.fr'];
    }
}
