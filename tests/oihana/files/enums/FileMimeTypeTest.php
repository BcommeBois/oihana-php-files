<?php

namespace oihana\files\enums ;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileMimeType::class)]
class FileMimeTypeTest extends TestCase
{
    protected function setUp(): void
    {
        FileMimeType::resetCaches();
        FileExtension::resetCaches();
    }

    public function testGetExtensionReturnsCorrectExtensionForSingleMimeType(): void
    {
        // Example: ‘audio/mpeg’ must correspond to .mp3
        $extension = FileMimeType::getExtension(FileMimeType::MP3 );
        $this->assertSame(FileExtension::MP3 , $extension );
    }

    public function testGetExtensionReturnsNullForUnknownMimeType(): void
    {
        $unknownMime = 'application/unknown-mime-type';
        $extension = FileMimeType::getExtension($unknownMime);
        $this->assertNull($extension);
    }

    public function testGetExtensionReturnsCorrectExtensionForMimeTypeWithMultipleValues(): void
    {
        // Example for a MIME type stored as an array in FileMimeType (e.g. M4A)
        // Here we test the ability to retrieve the correct extension via getConstant
        $extensions = FileMimeType::getExtension(FileMimeType::M4A[0]);
        $this->assertSame(FileExtension::M4A, $extensions);
        $extensions2 = FileMimeType::getExtension(FileMimeType::M4A[1]);
        $this->assertSame(FileExtension::M4A, $extensions2);
    }

    public function testGetExtensionUsesCache(): void
    {
        // To check that the cache is being used, call twice
        $firstCall = FileMimeType::getExtension(FileMimeType::MP3);
        $secondCall = FileMimeType::getExtension(FileMimeType::MP3);
        $this->assertSame($firstCall, $secondCall);
    }

    public function testGetExtensionReturnsNullIfMimeUnknown()
    {
        $result = FileMimeType::getExtension('unknown/mime');
        $this->assertNull($result);
    }

    public function testGetExtensionReturnsExtensionForKnownMime()
    {
        $result = FileMimeType::getExtension('text/plain');
        $this->assertEquals('.txt', $result);
    }

    public function testGetExtensionReturnsMultipleExtensionForKnownMime()
    {
        $result = FileMimeType::getExtension('application/octet-stream' );
        $this->assertEquals(['.app', '.db','.enc','.tar.gz.enc'] , $result);
    }
    public function testGetFromExtensionKnown()
    {
        $this->assertEquals('image/jpeg',  FileMimeType::getFromExtension(FileExtension::JPEG ));
        $this->assertEquals('image/jpeg',  FileMimeType::getFromExtension('.jpg'));
        $this->assertEquals('image/jpeg', FileMimeType::getFromExtension('jpg') );
    }

    public function testGetFromExtensionUnknown()
    {
        $result = FileMimeType::getFromExtension('.unknownext');
        $this->assertNull($result);
    }
}