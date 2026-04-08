<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

/**
 * Throwaway config for the Context/System bucket reorganization.
 *
 * Rector 2.x removed RenameNamespaceRector, so we build an explicit
 * class-by-class map by walking every PHP file under src/, tests/ and
 * fixtures/ and extracting its declared namespace + class name. This is
 * order-independent: the map is computed by namespace pattern, not file
 * path, so this config produces the same map whether the BC folders have
 * already been moved or not.
 */

$projectDir = __DIR__;

$contextBcs = ['Clinic', 'ClinicalCare', 'Animal', 'Client', 'Scheduling'];
$systemBcs  = ['IdentityAccess', 'AccessControl', 'Translation'];

$bucketByBc = [];
foreach ($contextBcs as $bc) {
    $bucketByBc[$bc] = 'Context';
}
foreach ($systemBcs as $bc) {
    $bucketByBc[$bc] = 'System';
}

$rewriteNamespace = static function (string $namespace) use ($bucketByBc): ?string {
    foreach ($bucketByBc as $bc => $bucket) {
        // App\<BC>\... -> App\<bucket>\<BC>\...
        $rewritten = preg_replace(
            '/^App\\\\(' . preg_quote($bc, '/') . ')(?=\\\\|$)/',
            'App\\\\' . $bucket . '\\\\$1',
            $namespace,
            1,
            $count1,
        );
        if ($count1 > 0) {
            return $rewritten;
        }

        // App\Tests\(Unit|Integration)\<BC>\... -> ...\<bucket>\<BC>\...
        $rewritten = preg_replace(
            '/^App\\\\Tests\\\\(Unit|Integration)\\\\(' . preg_quote($bc, '/') . ')(?=\\\\|$)/',
            'App\\\\Tests\\\\$1\\\\' . $bucket . '\\\\$2',
            $namespace,
            1,
            $count2,
        );
        if ($count2 > 0) {
            return $rewritten;
        }
    }

    return null;
};

$scanRoots = ['src', 'tests', 'fixtures'];
$renameMap = [];

foreach ($scanRoots as $root) {
    $rootDir = $projectDir . '/' . $root;
    if (!is_dir($rootDir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if (false === $contents) {
            continue;
        }

        if (!preg_match('/^namespace\s+([^;]+);/m', $contents, $nsMatch)) {
            continue;
        }
        $namespace = trim($nsMatch[1]);

        $newNamespace = $rewriteNamespace($namespace);
        if (null === $newNamespace) {
            continue;
        }

        if (!preg_match_all(
            '/^(?:abstract\s+|final\s+|readonly\s+|final\s+readonly\s+|abstract\s+readonly\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m',
            $contents,
            $clsMatches,
        )) {
            continue;
        }

        foreach ($clsMatches[1] as $shortName) {
            $oldFqcn = $namespace . '\\' . $shortName;
            $newFqcn = $newNamespace . '\\' . $shortName;
            if ($oldFqcn !== $newFqcn) {
                $renameMap[$oldFqcn] = $newFqcn;
            }
        }
    }
}

return RectorConfig::configure()
    ->withPaths([
        $projectDir . '/src',
        $projectDir . '/tests',
        $projectDir . '/fixtures',
        $projectDir . '/migrations',
    ])
    ->withSkip([
        $projectDir . '/rector.php',
    ])
    ->withConfiguredRule(RenameClassRector::class, $renameMap);
