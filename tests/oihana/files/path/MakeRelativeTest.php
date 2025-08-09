<?php

namespace oihana\files\path ;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use InvalidArgumentException;

#[CoversFunction('oihana\files\makeRelative')]
final class MakeRelativeTest extends TestCase
{

    #[DataProvider('pathProvider')]
    public function testMakeRelative(string $expected, string $path, string $basePath): void
    {
        $this->assertSame($expected, makeRelative($path, $basePath));
    }

    public static function pathProvider(): array
    {
        return [
            // [expected, path, basePath]

            // Case: Unix
            'target is subdirectory' => ['dir/file.txt', '/var/www/dir/file.txt', '/var/www'],
            'target is sibling' => ['../bar/file.txt', '/var/bar/file.txt', '/var/foo'],
            'target is parent' => ['../', '/var', '/var/www'],
            'complex path' => ['../../foo/bar', '/home/foo/bar', '/home/user/project'],
            'identical paths' => ['', '/var/www', '/var/www'],
            'base is root' => ['var/www', '/var/www', '/'],

            // Case: Windows
            'windows subdirectory' => ['Users/Test', 'C:/Windows/Users/Test', 'C:/Windows'],
            'windows sibling' => ['../System32', 'C:/Windows/System32', 'C:/Windows/Fonts'],

            // Case: Schemas
            'phar scheme subdirectory' => ['src/app.php', 'phar:///usr/app/src/app.php', 'phar:///usr/app'],
            'phar scheme sibling' => ['../config', 'phar:///usr/config', 'phar:///usr/app'],

            // Special Case
            'trailing slashes' => ['../bar', '/foo/bar/', '/foo/baz/'],
        ];
    }

    public function testThrowsExceptionForDifferentRoots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('different roots');
        makeRelative('D:/path/file', 'C:/path/base');
    }

    public function testThrowsExceptionForDifferentSchemes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('different roots');
        makeRelative('vfs:///path/file', 'phar:///path/base');
    }

    public function testThrowsExceptionForNonAbsolutePaths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Both paths must be absolute');
        makeRelative('relative/path', '/absolute/base');
    }
}