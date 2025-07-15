<?php

namespace oihana\files\enums ;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileExtension::class)]
class FileExtensionTest extends TestCase
{
    public function testGetAllReturnsAllExtensions(): void
    {
        $all = FileExtension::getAll();

        $this->assertIsArray($all);
        $this->assertNotEmpty($all);

        // Check that a few key extensions are present
        $this->assertContains('.mp3', $all);
        $this->assertContains('.tar.gz', $all);
        $this->assertContains('.pdf', $all);
        $this->assertContains('.woff2', $all);
        $this->assertContains('.exe', $all);
    }

    public function testGetMultiplePartExtensionsReturnsOnlyMultiDotExtensions(): void
    {
        $multi = FileExtension::getMultiplePartExtensions();

        $this->assertIsArray($multi);
        $this->assertNotEmpty($multi);

        // All returned extensions must contain at least two dots
        foreach ($multi as $ext)
        {
            $this->assertGreaterThanOrEqual(2 , substr_count($ext, '.') );
        }

        // Must contain known multi-parts extensions
        $this->assertContains('.tar.gz', $multi);
        $this->assertContains('.tar.gz.enc', $multi);
        $this->assertContains('.tar.bz2', $multi);

        // A simple extension must not be in the list
        $this->assertNotContains('.mp3', $multi);
        $this->assertNotContains('.pdf', $multi);
    }

    public function testGetMultiplePartExtensionsIsCached(): void
    {
        $firstCall = FileExtension::getMultiplePartExtensions();
        $secondCall = FileExtension::getMultiplePartExtensions();
        $this->assertSame($firstCall, $secondCall);
    }

    public function testGetMimeTypeReturnsCorrectMimeTypeOrNull(): void
    {
        // Retrieve the complete list of mimetype mappings to check consistency
        $allMimeTypes = FileMimeType::getAll();
        foreach ( FileExtension::getAll() as $constantName => $extension)
        {
            $mimeType         = FileExtension::getMimeType( $extension );
            $expectedMimeType = $allMimeTypes[$constantName] ?? null;
            // The mimeType returned must match that defined in FileMimeType (or null if absent).
            $this->assertEquals
            (
                $expectedMimeType,
                $mimeType,
                "Le mimetype pour l'extension " . var_export($extension, true) . " doit être " . var_export($expectedMimeType, true) . "."
            );
        }

        // Test for non-existent extension (value not in constants)
        $this->assertNull(FileExtension::getMimeType('.inexistant'));

        // Test with value equal to null (must return null)
        $this->assertNull(FileExtension::getMimeType(''));
    }
}