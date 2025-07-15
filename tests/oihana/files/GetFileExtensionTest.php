<?php

namespace oihana\files ;

use PHPUnit\Framework\TestCase;

final class GetFileExtensionTest extends TestCase
{
    public function testSimpleExtensions()
    {
        $this->assertSame('.txt', getFileExtension('/path/to/file.txt'));
        $this->assertSame('.pdf', getFileExtension('/another/path/DOCUMENT.PDF')); // test case insensible
    }

    public function testNoExtension()
    {
        $this->assertNull(getFileExtension('/path/to/file'));
        $this->assertNull(getFileExtension('filename'));
    }

    public function testMultiPartExtensions()
    {
        $multiPart = ['.tar.gz', '.blade.php'];

        $this->assertSame('.tar.gz', getFileExtension('/archive.tar.gz', $multiPart));
        $this->assertSame('.blade.php', getFileExtension('/view.blade.php', $multiPart));

        // Sans passer le multi-part, le retour sera juste l'extension simple
        $this->assertSame('.php', getFileExtension('/view.blade.php'));
    }

    public function testWindowsPath()
    {
        $this->assertSame('.txt', getFileExtension('C:\\path\\to\\file.txt'));
    }

    public function testLowercaseExtensions()
    {
        $this->assertSame('.txt', getFileExtension('/path/to/file.TXT'));
        $this->assertSame('.pdf', getFileExtension('/another/path/document.PDF'));

        // Test avec $lowercase = false
        $this->assertSame('.TXT', getFileExtension('/path/to/file.TXT', null, false));
        $this->assertSame('.PDF', getFileExtension('/another/path/document.PDF', null, false));
    }


    public function testMultiPartExtensionsCaseSensitivity()
    {
        $multiPart = ['.tar.gz', '.blade.php'];

        // Test avec $lowercase = true (par défaut)
        $this->assertSame('.tar.gz', getFileExtension('/archive.TAR.GZ', $multiPart));
        $this->assertSame('.blade.php', getFileExtension('/view.BLADE.PHP', $multiPart));

        // Test avec $lowercase = false
        $this->assertSame('.TAR.GZ', getFileExtension('/archive.TAR.GZ', $multiPart, false));
        $this->assertSame('.BLADE.PHP', getFileExtension('/view.BLADE.PHP', $multiPart, false));
    }

    public function testWindowsPathCaseSensitivity()
    {
        // Test avec $lowercase = true (par défaut)
        $this->assertSame('.txt', getFileExtension('C:\\path\\to\\file.TXT'));

        // Test avec $lowercase = false
        $this->assertSame('.TXT', getFileExtension('C:\\path\\to\\file.TXT', null, false));
    }
}