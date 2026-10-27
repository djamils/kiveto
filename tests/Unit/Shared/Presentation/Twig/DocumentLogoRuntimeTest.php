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
        $storage->storeAt('logos/clinic.JPEG', 'JPEG');
        $storage->storeAt('logos/clinic.bmp', 'BMP');
        $this->runtime = new DocumentLogoRuntime($storage);
    }

    protected function tearDown(): void
    {
        foreach (['logos/clinic.svg', 'logos/clinic.gif', 'logos/clinic.JPEG', 'logos/clinic.bmp'] as $file) {
            @unlink($this->root . '/' . $file);
        }

        @rmdir($this->root . '/logos');
        @rmdir($this->root);
    }

    public function testAStoredLogoIsInlinedWithItsContentType(): void
    {
        self::assertSame('data:image/svg+xml;base64,' . base64_encode('<svg/>'), $this->runtime->dataUri('logos/clinic.svg'));
    }

    /**
     * N13: GIF and JPEG logos keep their type, whatever the extension's case.
     */
    public function testGifAndJpegLogosOfAnyCaseKeepTheirType(): void
    {
        self::assertSame('data:image/gif;base64,' . base64_encode('GIF'), $this->runtime->dataUri('logos/clinic.gif'));
        self::assertSame('data:image/jpeg;base64,' . base64_encode('JPEG'), $this->runtime->dataUri('logos/clinic.JPEG'));
    }

    public function testAnUnknownExtensionIsSentAsPng(): void
    {
        self::assertSame('data:image/png;base64,' . base64_encode('BMP'), $this->runtime->dataUri('logos/clinic.bmp'));
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
