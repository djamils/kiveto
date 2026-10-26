<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Notification is reached only through its Application commands and
 * queries and its integration events; it knows nothing of the contexts that
 * use it, nor of the presentation.
 */
final class NotificationBoundaryTest extends TestCase
{
    private const string BC        = 'App\System\Notification\\';
    private const string BC_FOLDER = 'System/Notification/';
    private const string SRC       = __DIR__ . '/../../../src/';

    /** What code outside the context may import from it. */
    private const string PUBLIC_API = '/^App\\\System\\\Notification\\\(Application\\\(Command|Query)\\\.+|Domain\\\Event\\\[A-Za-z]+IntegrationEvent)$/';

    /** What the context itself may not import. */
    private const array FORBIDDEN_INSIDE = ['App\Context\\', 'App\Presentation\\'];

    public function testTheSourceRespectsTheBoundary(): void
    {
        $files = [];

        foreach (new Finder()->files()->in(self::SRC)->name('*.php') as $file) {
            $files[$file->getRelativePathname()] = $file->getContents();
        }

        self::assertGreaterThan(100, \count($files));
        self::assertSame([], self::violations($files));
    }

    /**
     * AC 42: a context importing the aggregate fails, naming the file.
     */
    public function testAContextImportingTheDomainIsReported(): void
    {
        $violations = self::violations([
            'Context/Billing/Somewhere.php' => <<<'PHP'
                <?php
                namespace App\Context\Billing;
                use App\System\Notification\Domain\Message\OutboundMessage;
                use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
                use App\System\Notification\Domain\Event\OutboundMessageStatusChangedIntegrationEvent;
                use App\System\Notification\{Infrastructure\Rendering\TwigMessageRenderer, Application\Query\GetSmsUsage\GetSmsUsage};
                PHP,
        ]);

        self::assertSame([
            'Context/Billing/Somewhere.php imports App\System\Notification\Domain\Message\OutboundMessage',
            'Context/Billing/Somewhere.php imports App\System\Notification\Infrastructure\Rendering\TwigMessageRenderer',
        ], $violations);
    }

    public function testTheContextImportingAContextOrThePresentationIsReported(): void
    {
        $violations = self::violations([
            self::BC_FOLDER . 'Application/Leak.php' => <<<'PHP'
                <?php
                namespace App\System\Notification\Application;
                use App\Context\Billing\Domain\Invoice;
                use App\Presentation\Clinic\ViewModel\Settings\SmsUsageCard;
                use App\System\Notification\Domain\Message\OutboundMessage;
                use App\Shared\Domain\Time\ClockInterface;
                final class Leak
                {
                    public function __invoke(): void
                    {
                        $f = function () use ($x) {};
                    }
                }
                PHP,
        ]);

        self::assertSame([
            self::BC_FOLDER . 'Application/Leak.php imports App\Context\Billing\Domain\Invoice',
            self::BC_FOLDER . 'Application/Leak.php imports App\Presentation\Clinic\ViewModel\Settings\SmsUsageCard',
        ], $violations);
    }

    /**
     * @param array<string, string> $files source by path relative to src/
     *
     * @return list<string>
     */
    private static function violations(array $files): array
    {
        $violations = [];

        foreach ($files as $path => $code) {
            $path   = str_replace('\\', '/', $path);
            $inside = str_starts_with($path, self::BC_FOLDER);

            foreach (self::imports($code) as $import) {
                $denied = $inside
                    ? array_any(self::FORBIDDEN_INSIDE, static fn (string $prefix): bool => str_starts_with($import, $prefix))
                    : str_starts_with($import, self::BC) && 1 !== preg_match(self::PUBLIC_API, $import);

                if ($denied) {
                    $violations[] = $path . ' imports ' . $import;
                }
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * The fully-qualified names a file imports with top-level `use`
     * statements, group uses expanded; closure `use` clauses are ignored.
     *
     * @return list<string>
     */
    private static function imports(string $code): array
    {
        $tokens = array_values(array_filter(
            \PhpToken::tokenize($code),
            static fn (\PhpToken $token): bool => !$token->is([\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT]),
        ));
        $imports = [];
        $depth   = 0;

        foreach ($tokens as $i => $token) {
            if ('{' === $token->text || $token->is([\T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES])) {
                ++$depth;
            } elseif ('}' === $token->text) {
                --$depth;
            }

            if (0 !== $depth || !$token->is(\T_USE)) {
                continue;
            }

            $statement = '';
            for ($j = $i + 1; isset($tokens[$j]) && ';' !== $tokens[$j]->text; ++$j) {
                $statement .= $tokens[$j]->is(\T_AS) ? ' as ' : $tokens[$j]->text;
            }

            $statement = preg_replace('/^(function|const)(?=[A-Z\\\])/', '', $statement) ?? $statement;

            if (1 === preg_match('/^(.+?)\\\?\{(.*)\}$/', $statement, $group)) {
                foreach (explode(',', $group[2]) as $member) {
                    if ('' !== mb_trim($member)) {
                        $imports[] = mb_rtrim($group[1], '\\') . '\\' . self::withoutAlias($member);
                    }
                }

                continue;
            }

            foreach (explode(',', $statement) as $member) {
                $imports[] = self::withoutAlias($member);
            }
        }

        return array_map(static fn (string $import): string => mb_ltrim($import, '\\'), $imports);
    }

    private static function withoutAlias(string $member): string
    {
        return mb_trim(explode(' as ', $member)[0]);
    }
}
