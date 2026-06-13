<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;
use function oihana\files\moveFile;

class MoveFileTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/move_file_' . uniqid();
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
    public function testMovesToAnExplicitPath(): void
    {
        $source = $this->makeSource( 'hello' );
        $dest   = $this->tmpDir . '/moved.txt';

        $this->assertTrue( moveFile( $source , $dest ) );
        $this->assertFileExists( $dest );
        $this->assertStringEqualsFile( $dest , 'hello' );
        $this->assertFileDoesNotExist( $source ); // source removed
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testMovesIntoAnExistingDirectory(): void
    {
        $source = $this->makeSource();
        $dir    = $this->tmpDir . '/target';
        makeDirectory( $dir );

        $this->assertTrue( moveFile( $source , $dir ) );
        $this->assertFileExists( $dir . '/source.txt' );
        $this->assertFileDoesNotExist( $source );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testCreatesMissingDestinationDirectory(): void
    {
        $source = $this->makeSource();
        $dest   = $this->tmpDir . '/nested/deep/moved.txt';

        $this->assertTrue( moveFile( $source , $dest ) );
        $this->assertFileExists( $dest );
    }

    public function testThrowsWhenDestinationExistsAndOverwriteIsFalse(): void
    {
        $source = $this->makeSource();
        $dest   = $this->tmpDir . '/moved.txt';
        file_put_contents( $dest , 'old' );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'already exists' );
        moveFile( $source , $dest , overwrite: false );
    }

    public function testThrowsWhenSourceIsMissing(): void
    {
        $this->expectException( FileException::class );
        moveFile( $this->tmpDir . '/missing.txt' , $this->tmpDir . '/moved.txt' );
    }

    public function testThrowsWhenDirectoryIsMissingAndCreationDisabled(): void
    {
        $source = $this->makeSource();
        $dest   = $this->tmpDir . '/nope/moved.txt';

        $this->expectException( DirectoryException::class );
        $this->expectExceptionMessage( 'does not exist' );
        moveFile( $source , $dest , createDirectory: false );
    }
}
