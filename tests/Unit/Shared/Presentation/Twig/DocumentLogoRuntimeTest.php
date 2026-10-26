<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation\Twig;

use App\Shared\Infrastructure\Storage\LocalFileSystemStorage;
use App\Shared\Presentation\Twig\DocumentLogoExtension;
use App\Shared\Presentation\Twig\DocumentLogoRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentLogoRuntime::class)]
#[CoversClass(DocumentLogoExtension::class)]
final class DocumentLogoRuntimeTest extends TestCase
{
    private string $root;
    private DocumentLogoRuntime $runtime;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kiveto-logo-' . bin2hex(random_bytes(4));
        $storage    = new LocalFileSystemStorage($this->root, '');
        $storage->storeAt('logos/clinic.svg', '<svg/>');
        $storage->storeAt('logos/clinic.gif', 'GIF');
        $this->runtime = new DocumentLogoRuntime($storage);
    }

    protected function tearDown(): void
    {
        foreach (['logos/clinic.svg', 'logos/clinic.gif'] as $file) {
            @unlink($this->root . '/' . $file);
        }

        @rmdir($this->root . '/logos');
        @rmdir($this->root);
    }

    public function testAStoredLogoIsInlinedWithItsContentType(): void
    {
        self::assertSame('data:image/svg+xml;base64,' . base64_encode('<svg/>'), $this->runtime->dataUri('logos/clinic.svg'));
    }

    public function testAnUnknownExtensionIsSentAsPng(): void
    {
        self::assertSame('data:image/png;base64,' . base64_encode('GIF'), $this->runtime->dataUri('logos/clinic.gif'));
    }

    public function testNoLogoOrAMissingFilePrintsNoLogo(): void
    {
        self::assertNull($this->runtime->dataUri(null));
        self::assertNull($this->runtime->dataUri('logos/gone.png'));
    }

    public function testTheExtensionExposesTheFunctionUnderItsTemplateName(): void
    {
        $functions = new DocumentLogoExtension($this->runtime)->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('clinic_logo_data_uri', $functions[0]->getName());
    }
}
