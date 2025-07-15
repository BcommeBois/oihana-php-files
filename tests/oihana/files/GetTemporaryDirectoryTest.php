<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;

final class GetTemporaryDirectoryTest extends TestCase
{
    /**
     * @throws DirectoryException
     */
    public function testReturnsSysTempDirByDefault(): void
    {
        $result = getTemporaryDirectory();
        $this->assertSame
        (
            rtrim( sys_get_temp_dir() , DIRECTORY_SEPARATOR ) ,
            $result ,
            'By default, the temporary path must be normalized sys_get_temp_dir().'
        );
    }

    /**
     * @throws DirectoryException
     */
    public function testAppendsStringPathCorrectly(): void
    {
        $subDirectory = 'myapp';
        $expected = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $subDirectory;

        $result = getTemporaryDirectory( $subDirectory );

        $this->assertSame(rtrim($expected, DIRECTORY_SEPARATOR), $result);
    }

    /**
     * @throws DirectoryException
     */
    public function testAppendsMultipleSubpaths(): void
    {
        $segments = ['myapp', 'cache'];
        $expected = sys_get_temp_dir() . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);

        $result = getTemporaryDirectory($segments);

        $this->assertSame(rtrim($expected, DIRECTORY_SEPARATOR), $result);
    }

    /**
     * @throws DirectoryException
     */
    public function testHandlesEmptyArray(): void
    {
        $result = getTemporaryDirectory([]);
        $this->assertSame
        (
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR),
            $result,
            'An empty array must return sys_get_temp_dir().'
        );
    }

    /**
     * @throws DirectoryException
     */
    public function testSkipsEmptySubpathSegments(): void
    {
        $segments = ['', null, 'foo', '', 'bar'];
        $expected = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'foo' . DIRECTORY_SEPARATOR . 'bar';

        $result = getTemporaryDirectory($segments);
        $this->assertSame(rtrim($expected, DIRECTORY_SEPARATOR), $result);
    }

    public function testThrowsExceptionWhenAssertableAndInvalidPath(): void
    {
        $invalidPath = ['nonexistent_' . uniqid()];

        $this->expectException(DirectoryException::class);
        getTemporaryDirectory($invalidPath, assertable: true);
    }

    /**
     * @throws DirectoryException
     */
    public function testWorksWithAssertableOnSysTemp(): void
    {
        $result = getTemporaryDirectory( assertable: false );
        $this->assertDirectoryExists($result);
        $this->assertTrue(is_readable($result));
    }
}