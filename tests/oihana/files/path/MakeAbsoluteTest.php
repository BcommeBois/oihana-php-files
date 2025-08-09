<?php

namespace oihana\files\path ;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

#[CoversFunction('oihana\files\makeAbsolute')]
final class MakeAbsoluteTest extends TestCase
{
    /**
     * Test basePath vide : doit lancer une exception.
     */
    public function testEmptyBasePathThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The base path must be a non-empty string.');

        makeAbsolute('some/path', '');
    }

    /**
     * Test basePath non absolu : doit lancer une exception.
     */
    public function testNonAbsoluteBasePathThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not an absolute path');

        makeAbsolute('some/path', 'relative/base');
    }

    /**
     * Test path absolu : doit retourner le path canonicalisé.
     */
    public function testAbsolutePathReturnsCanonicalized(): void
    {
        $absPath = '/var/www/html/index.php';
        $basePath = '/var/www';

        $result = makeAbsolute($absPath, $basePath);

        $this->assertEquals
        (
            canonicalizePath($absPath),
            $result
        );
    }

    /**
     * Test chemin relatif simple avec base path absolu.
     */
    public function testRelativePathAppendedToBasePath(): void
    {
        $relative = 'images/photo.jpg';
        $basePath = '/var/www/html';

        $expected = canonicalizePath($basePath . '/' . $relative);

        $result = makeAbsolute($relative, $basePath);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test chemin avec schéma (ex: phar://).
     */
    public function testPathWithScheme(): void
    {
        $relative = 'dir/file.txt';

        // Use an Unix-style absolute path after the schema
        $basePath = 'phar:///tmp/archive.phar';

        // The expected must reflect the absolute path
        $expected = 'phar://' . canonicalizePath('/tmp/archive.phar/' . $relative);

        $result = makeAbsolute($relative, $basePath);

        $this->assertEquals($expected, $result);
    }

}