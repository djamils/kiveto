<?php

declare(strict_types=1);

namespace App\Tests\Support\Console;

use App\Fixtures\System\IdentityAccess\Factory\BackofficeUserFactory;
use App\System\IdentityAccess\Application\Service\BackupCodes;
use App\System\IdentityAccess\Domain\SecondFactor\BackupCode;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactorRepositoryInterface;
use App\System\IdentityAccess\Domain\SecondFactor\Totp;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

/**
 * Signs a back-office admin in to the console the way a browser does:
 * the password form, then a code of his authenticator app.
 */
trait SignsInToTheConsole
{
    protected const string CONSOLE = 'http://backoffice.kiveto.local';

    protected const string CONSOLE_TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /**
     * The backup codes given to the admin (in clear), by giveSecondFactor().
     *
     * @var list<string>
     */
    protected array $consoleBackupCodes = [];

    /**
     * An admin with a second factor; answers his id.
     */
    protected function createConsoleAdmin(string $email = 'djamil@kiveto.test', string $password = 'mot-de-passe-admin', bool $withSecondFactor = true): string
    {
        $admin = BackofficeUserFactory::new()->withEmail($email)->withPlainPassword($password)->create();
        $id    = $admin->getId()->toRfc4122();

        if ($withSecondFactor) {
            $this->giveSecondFactor($id);
        }

        return $id;
    }

    protected function giveSecondFactor(string $userId): void
    {
        $container = static::getContainer();
        $codes     = $container->get(BackupCodes::class);
        \assert($codes instanceof BackupCodes);
        $factors = $container->get(SecondFactorRepositoryInterface::class);
        \assert($factors instanceof SecondFactorRepositoryInterface);

        $fresh                    = $codes->fresh();
        $this->consoleBackupCodes = $fresh['plain'];

        $factors->save(SecondFactor::reconstitute(
            UserId::fromString($userId),
            TotpSecret::fromBase32(self::CONSOLE_TOTP_SECRET),
            new \DateTimeImmutable('-1 day'),
            null,
            array_map(BackupCode::unused(...), $fresh['hashes']),
        ));
    }

    protected function consoleCode(int $stepOffset = 0): string
    {
        return Totp::code(TotpSecret::fromBase32(self::CONSOLE_TOTP_SECRET), Totp::stepAt(new \DateTimeImmutable()) + $stepOffset);
    }

    /**
     * The password step; the browser is then on the code page.
     */
    protected function submitConsolePassword(KernelBrowser $browser, string $email = 'djamil@kiveto.test', string $password = 'mot-de-passe-admin'): void
    {
        $crawler = $browser->request('GET', self::CONSOLE . '/login');
        $browser->submit($crawler->filter('form')->form(['email' => $email, 'password' => $password]));
    }

    protected function submitConsoleCode(KernelBrowser $browser, string $code, bool $trust = false): void
    {
        $crawler = $browser->request('GET', self::CONSOLE . '/login/verification');
        $form    = $crawler->filter('form')->form(['code' => $code]);

        if ($trust) {
            $field = $form['trust_device'];
            \assert($field instanceof ChoiceFormField);
            $field->tick();
        }

        $browser->submit($form);
    }

    protected function signInToTheConsole(KernelBrowser $browser, string $email = 'djamil@kiveto.test', string $password = 'mot-de-passe-admin'): void
    {
        $this->submitConsolePassword($browser, $email, $password);
        $this->submitConsoleCode($browser, $this->consoleCode());
    }
}
