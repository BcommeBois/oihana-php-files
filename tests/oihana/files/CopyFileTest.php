<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\copyFile;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

class CopyFileTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/copy_file_' . uniqid();
        makeDirectory( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir );
    }

    private function makeSource( string $content = 'payload' ): string
    {
        $path = $this->tmpDir . '/source.txt';
        file_put_contents( $path , $content );
        return $path;
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testCopiesToAnExplicitPath(): void
    {
        $source = $this->makeSource( 'hello' );
        $dest   = $this->tmpDir . '/copy.txt';

        $this->assertTrue( copyFile( $source , $dest ) );
        $this->assertFileExists( $dest );
        $this->assertStringEqualsFile( $dest , 'hello' );
        $this->assertFileExists( $source ); // source preserved
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testCopiesIntoAnExistingDirectory(): void
    {
        $source = $this->makeSource();
        $dir    = $this->tmpDir . '/target';
        makeDirectory( $dir );

        $this->assertTrue( copyFile( $source , $dir ) );
        $this->assertFileExists( $dir . '/source.txt' );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testCreatesMissingDestinationDirectory(): void
    {
        $source = $this->makeSource();
        $dest   = $this->tmpDir . '/nested/deep/copy.txt';

        $this->assertTrue( copyFile( $source , $dest ) );
        $this->assertFileExists( $dest );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testOverwritesByDefault(): void
    {
        $source = $this->makeSource( 'new' );
        $dest   = $this->tmpDir . '/copy.txt';
        file_put_contents( $dest , 'old' );

        $this->assertTrue( copyFile( $source , $dest ) );
        $this->assertStringEqualsFile( $dest , 'new' );
    }

    public function testThrowsWhenDestinationExistsAndOverwriteIsFalse(): void
    {
        $source = $this->makeSource();
        $dest   = $this->tmpDir . '/copy.txt';
        file_put_contents( $dest , 'old' );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'already exists' );
        copyFile( $source , $dest , overwrite: false );
    }

    public function testThrowsWhenSourceIsMissing(): void
    {
        $this->expectException( FileException::class );
        copyFile( $this->tmpDir . '/missing.txt' , $this->tmpDir . '/copy.txt' );
    }

    /**
     * @throws DirectoryException
     */
    public function testThrowsWhenSourceAndDestinationAreTheSameFile(): void
    {
        $source = $this->makeSource();

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'same file' );
        copyFile( $source , $source );
    }

    public function testThrowsWhenDirectoryIsMissingAndCreationDisabled(): void
    {
        $source = $this->makeSource();
        $dest   = $this->tmpDir . '/nope/copy.txt';

        $this->expectException( DirectoryException::class );
        $this->expectExceptionMessage( 'does not exist' );
        copyFile( $source , $dest , createDirectory: false );
    }

    public function testThrowsWhenCopyFails(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' );
        }

        $source = $this->makeSource();
        $dest   = $this->tmpDir . '/readonly.txt';
        file_put_contents( $dest , 'locked' );
        chmod( $dest , 0444 ); // read-only: copy() cannot open it for writing

        try
        {
            $this->expectException( FileException::class );
            $this->expectExceptionMessage( 'Failed to copy' );
            copyFile( $source , $dest ); // overwrite default true
        }
        finally
        {
            @chmod( $dest , 0644 );
        }
    }
}
