<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;
use function oihana\files\touchFile;

class TouchFileTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/touch_file_' . uniqid();
        makeDirectory( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testCreatesAMissingFile(): void
    {
        $file = $this->tmpDir . '/marker';

        $this->assertSame( $file , touchFile( $file ) );
        $this->assertFileExists( $file );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testSetsAnExplicitModificationTime(): void
    {
        $file  = $this->tmpDir . '/dated';
        $mtime = 1_000_000_000;

        touchFile( $file , $mtime );

        clearstatcache();
        $this->assertSame( $mtime , filemtime( $file ) );
        $this->assertSame( $mtime , fileatime( $file ) ); // atime defaults to mtime
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testSetsDistinctAccessTime(): void
    {
        $file  = $this->tmpDir . '/dated2';
        $mtime = 1_000_000_000;
        $atime = 1_111_111_111;

        touchFile( $file , $mtime , $atime );

        clearstatcache();
        $this->assertSame( $mtime , filemtime( $file ) );
        $this->assertSame( $atime , fileatime( $file ) );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testCreatesMissingParentDirectory(): void
    {
        $file = $this->tmpDir . '/nested/deep/marker';

        touchFile( $file );
        $this->assertFileExists( $file );
    }

    public function testThrowsWhenTouchFails(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' );
        }

        $readOnly = $this->tmpDir . '/ro';
        mkdir( $readOnly , 0555 );

        try
        {
            $this->expectException( FileException::class );
            $this->expectExceptionMessage( 'Failed to touch' );
            touchFile( $readOnly . '/marker' ); // dir exists (read-only) -> makeDirectory skipped, touch fails
        }
        finally
        {
            @chmod( $readOnly , 0755 );
        }
    }
}
