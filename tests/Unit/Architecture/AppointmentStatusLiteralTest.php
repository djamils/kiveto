<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Only Scheduling knows what an appointment status means (AC 89): outside
 * `src/Context/Scheduling`, no code writes an appointment status literal.
 * Presentation reads the `occupiesSlot`, `isPending` and `isWithdrawn` flags
 * the Scheduling read models compute, and other contexts keep the status
 * columns they read as opaque strings. Terminal statuses shared with other
 * lifecycles (`CANCELLED`, `COMPLETED`, `NO_SHOW`) are not scanned.
 */
final class AppointmentStatusLiteralTest extends TestCase
{
    private const string SRC = __DIR__ . '/../../../src/';

    /** The appointment statuses no other context may name. */
    private const string LITERAL = '/([\'"])(PLANNED|PENDING_CLINIC_CONFIRMATION|DECLINED)\1/';

    public function testNoCodeOutsideSchedulingNamesAnAppointmentStatus(): void
    {
        $files = [];

        foreach (new Finder()->files()->in([self::SRC . 'Presentation', self::SRC . 'Context'])->name('*.php') as $file) {
            $files[substr($file->getPathname(), \strlen(self::SRC))] = $file->getContents();
        }

        self::assertGreaterThan(100, \count($files));
        self::assertSame([], self::offences($files));
    }

    public function testALiteralOutsideSchedulingIsReported(): void
    {
        $offences = self::offences([
            'Presentation/Clinic/ViewModel/Leak.php'            => "<?php\n\$upcoming = 'PLANNED' === \$item->status;\n",
            'Context/Consultation/Leak.php'                     => "<?php\n\$sql = \"WHERE status IN ('PENDING_CLINIC_CONFIRMATION')\";\n\$x = \"DECLINED\";\n",
            'Context/Scheduling/Domain/Allowed.php'             => "<?php\n\$s = 'PLANNED';\n",
            'Presentation/Clinic/ViewModel/NotAStatusLabel.php' => "<?php\n\$s = 'PLANNED_VISIT' . 'CANCELLED';\n",
        ]);

        self::assertSame([
            'Presentation/Clinic/ViewModel/Leak.php:2 \'PLANNED\'',
            'Context/Consultation/Leak.php:2 \'PENDING_CLINIC_CONFIRMATION\'',
            'Context/Consultation/Leak.php:3 "DECLINED"',
        ], $offences);
    }

    /**
     * @param array<string, string> $files path relative to `src/` → contents
     *
     * @return list<string> `path:line literal` for each literal found
     */
    private static function offences(array $files): array
    {
        $offences = [];

        foreach ($files as $path => $contents) {
            if (str_starts_with($path, 'Context/Scheduling/')) {
                continue;
            }

            foreach (explode("\n", $contents) as $index => $line) {
                if (preg_match_all(self::LITERAL, $line, $matches) > 0) {
                    foreach ($matches[0] as $literal) {
                        $offences[] = \sprintf('%s:%d %s', $path, $index + 1, $literal);
                    }
                }
            }
        }

        return $offences;
    }
}
