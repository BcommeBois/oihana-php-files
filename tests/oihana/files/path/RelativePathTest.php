<?php

namespace oihana\files\path ;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversFunction('oihana\files\path\relativePath')]
class RelativePathTest extends TestCase
{
    /**
     * Test basic relative path calculations
     */
    public function testBasicRelativePaths(): void
    {
        // Child to parent
        $this->assertEquals('c', relativePath('/a/b/c', '/a/b'));
        $this->assertEquals('c/d', relativePath('/a/b/c/d', '/a/b'));

        // Parent to child
        $this->assertEquals('..', relativePath('/a/b', '/a/b/c'));
        $this->assertEquals('../..', relativePath('/a/b', '/a/b/c/d'));

        // Sibling directories
        $this->assertEquals('../c', relativePath('/a/c', '/a/b'));
        $this->assertEquals('../c/d', relativePath('/a/c/d', '/a/b'));

        // More complex paths
        $this->assertEquals('../../c/d', relativePath('/a/c/d', '/a/b/e'));
        $this->assertEquals('../../../x/y', relativePath('/x/y', '/a/b/c'));
    }

    /**
     * Test same path scenarios
     */
    public function testSamePaths(): void
    {
        $this->assertEquals('.', relativePath('/a/b/c', '/a/b/c'));
        $this->assertEquals('.', relativePath('/', '/'));
        $this->assertEquals('.', relativePath('/home', '/home'));
    }

    /**
     * Test root directory scenarios
     */
    public function testRootDirectory(): void
    {
        $this->assertEquals('a', relativePath('/a', '/'));
        $this->assertEquals('a/b', relativePath('/a/b', '/'));
        $this->assertEquals('..', relativePath('/', '/a'));  // Corrigé: '/' relatif à '/a' devrait être '.'
        $this->assertEquals('../..', relativePath('/', '/a/b'));
    }

    /**
     * Test relative paths (both target and base are relative)
     */
    public function testBothRelativePaths(): void
    {
        $this->assertEquals('c', relativePath('a/b/c', 'a/b'));
        $this->assertEquals('..', relativePath('a/b', 'a/b/c'));
        $this->assertEquals('../c', relativePath('a/c', 'a/b'));
        $this->assertEquals('../../c/d', relativePath('a/c/d', 'a/b/e'));
        $this->assertEquals('.', relativePath('a/b', 'a/b'));
    }

    /**
     * Test empty base path scenarios
     */
    public function testEmptyBasePath(): void
    {
        $this->assertEquals('a/b/c', relativePath('/a/b/c', '/'));
        $this->assertEquals('a/b/c', relativePath('a/b/c', ''));
    }

    /**
     * Test paths with special characters and edge cases
     */
    public function testSpecialCharacters(): void
    {
        $this->assertEquals('../file with spaces', relativePath('/a/file with spaces', '/a/b'));
        $this->assertEquals('..', relativePath('/a/b', '/a/b/file.txt'));
        $this->assertEquals('file.txt', relativePath('/a/b/file.txt', '/a/b'));
    }

    /**
     * Test Windows-style paths (if supported by canonicalizePath)
     */
    public function testWindowsPaths(): void
    {
        // These tests assume canonicalizePath normalizes Windows paths
        $this->assertEquals('c', relativePath('C:\\a\\b\\c', 'C:\\a\\b'));
        $this->assertEquals('..', relativePath('C:\\a\\b', 'C:\\a\\b\\c'));
        $this->assertEquals('../c', relativePath('C:\\a\\c', 'C:\\a\\b'));
    }

    /**
     * Test exception: relative target path with absolute base path
     */
    public function testRelativeTargetAbsoluteBaseException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The target path "a/b" is relative, but the base path "/c/d" is absolute');

        relativePath('a/b', '/c/d');
    }

    /**
     * Test exception: absolute target path with relative base path
     */
    public function testAbsoluteTargetRelativeBaseException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The absolute path "/a/b" cannot be made relative to the relative path "c/d"');

        relativePath('/a/b', 'c/d');
    }

    /**
     * Test exception: different roots (Windows drives)
     */
    public function testDifferentRootsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be made relative to');
        $this->expectExceptionMessage('because they have different roots');

        // This assumes canonicalizePath preserves drive letters
        relativePath('C:/a/b', 'D:/c/d');
    }

    /**
     * Test edge cases with dots in paths
     */
    public function testDotsInPaths(): void
    {
        // These tests assume canonicalizePath handles . and .. correctly
        $this->assertEquals('c', relativePath('/a/b/./c', '/a/b'));
        $this->assertEquals('c', relativePath('/a/b/../b/c', '/a/b'));
        $this->assertEquals('..', relativePath('/a/b/..', '/a/b'));
    }

    /**
     * Test performance with long paths
     */
    public function testLongPaths(): void
    {
        $longPath1 = '/' . str_repeat('a/', 100) . 'target';
        $longPath2 = '/' . str_repeat('a/', 99) . 'base';

        $result = relativePath($longPath1, $longPath2);
        $this->assertEquals('../a/target', $result);
    }

    /**
     * Data provider for comprehensive path testing
     */
    public static function pathProvider(): array
    {
        return
        [
        //  [ ---------------------------------------- ]
        //  [   target   |     base   |   expected     ]
        //  [ ---------------------------------------- ]
            [ '/a/b/c'   , '/a/b'     , 'c'            ] ,
            [ '/a/b'     , '/a/b/c'   , '..'           ] ,
            [ '/a/c'     , '/a/b'     , '../c'         ] ,
            [ '/a/b/c'   , '/a/b/c'   , '.'            ] ,
            [ '/'        , '/a'       , '..'           ] ,
            [ '/a'       , '/'        , 'a'            ] ,
            [ '/a/b/c/d' , '/a/b'     , 'c/d'          ] ,
            [ '/a/b'     , '/a/b/c/d' , '../..'        ] ,
            [ '/x/y'     , '/a/b/c'   , '../../../x/y' ] ,
            [ 'a/b/c'    , 'a/b'      , 'c'            ] ,
            [ 'a/b'      , 'a/b/c'    , '..'           ] ,
            [ 'a/c'      , 'a/b'      , '../c'         ] ,
            [ 'a/b/c'    , 'a/b/c'    , '.'            ] ,
        //  [ ---------------------------------------- ]
        ];
    }

    /**
     * Test multiple scenarios using data provider
     */
    #[DataProvider('pathProvider')]
    public function testMultipleScenarios(string $target, string $base, string $expected): void
    {
        $this->assertEquals($expected, relativePath($target, $base));
    }

    /**
     * Test error scenarios data provider
     */
    public static function errorPathProvider(): array
    {
        return
            [
            // [target, base, expected exception message part]
            [ 'a/b'  , '/c/d' , 'relative, but the base path'],
            [ '/a/b' , 'c/d'  , 'cannot be made relative to the relative path'],
            [ 'C:/a' , 'D:/b' , 'different roots' ],
        ];
    }

    /**
     * Test error scenarios using data provider
     */
    #[DataProvider('errorPathProvider')]
    public function testErrorScenarios(string $target, string $base, string $expectedMessagePart): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        relativePath($target, $base);
    }

    /**
     * Test with various path separators (assuming canonicalizePath handles them)
     */
    public function testPathSeparators(): void
    {
        // These tests assume canonicalizePath normalizes separators
        $this->assertEquals('c', relativePath('/a/b/c', '/a/b'));
        $this->assertEquals('c', relativePath('/a\\b\\c', '/a\\b'));
        $this->assertEquals('c', relativePath('\\a\\b\\c', '\\a\\b'));
    }
}