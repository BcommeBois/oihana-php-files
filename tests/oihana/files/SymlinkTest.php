<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\createSymlink;
use function oihana\files\deleteDirectory;
use function oihana\files\isSymlink;
use function oihana\files\makeDirectory;
use function oihana\files\readSymlink;

class SymlinkTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Symlink tests are not reliable on Windows.' );
        }

        $this->tmpDir = sys_get_temp_dir() . '/symlink_' . uniqid();
        makeDirectory( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        if ( isset( $this->tmpDir ) && is_dir( $this->tmpDir ) )
        {
            deleteDirectory( $this->tmpDir );
        }
    }

    private function makeTarget( string $name = 'target.txt' ): string
    {
        $path = $this->tmpDir . '/' . $name;
        file_put_contents( $path , 'data' );
        return $path;
    }

    /**
     * @throws FileException
     */
    public function testCreateAndReadSymlink(): void
    {
        $target = $this->makeTarget();
        $link   = $this->tmpDir . '/link';

        $this->assertTrue( createSymlink( $target , $link ) );
        $this->assertTrue( isSymlink( $link ) );
        $this->assertSame( $target , readSymlink( $link ) );
    }

    /**
     * @throws FileException
     */
    public function testCreateAllowsDanglingTarget(): void
    {
        $link = $this->tmpDir . '/dangling';

        createSymlink( $this->tmpDir . '/does-not-exist' , $link );
        $this->assertTrue( isSymlink( $link ) );
        $this->assertSame( $this->tmpDir . '/does-not-exist' , readSymlink( $link ) );
    }

    public function testCreateThrowsWhenEntryExistsWithoutOverwrite(): void
    {
        $target = $this->makeTarget();
        $link   = $this->tmpDir . '/link';
        file_put_contents( $link , 'occupied' );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'already exists' );
        createSymlink( $target , $link );
    }

    /**
     * @throws FileException
     */
    public function testCreateOverwritesExistingEntry(): void
    {
        $target = $this->makeTarget();
        $link   = $this->tmpDir . '/link';
        file_put_contents( $link , 'occupied' );

        $this->assertTrue( createSymlink( $target , $link , overwrite: true ) );
        $this->assertTrue( isSymlink( $link ) );
        $this->assertSame( $target , readSymlink( $link ) );
    }

    public function testCreateThrowsWhenSymlinkFails(): void
    {
        $target = $this->makeTarget();
        // Parent directory of the link does not exist -> symlink() fails.
        $link = $this->tmpDir . '/missing/link';

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'Failed to create the symlink' );
        createSymlink( $target , $link );
    }

    public function testIsSymlinkFalseForRegularFileAndMissing(): void
    {
        $file = $this->makeTarget();

        $this->assertFalse( isSymlink( $file ) );
        $this->assertFalse( isSymlink( $this->tmpDir . '/missing' ) );
    }

    public function testReadSymlinkThrowsWhenNotALink(): void
    {
        $file = $this->makeTarget();

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'not a symbolic link' );
        readSymlink( $file );
    }
}
