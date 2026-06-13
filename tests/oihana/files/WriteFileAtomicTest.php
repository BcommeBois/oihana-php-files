<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;
use function oihana\files\writeFileAtomic;

class WriteFileAtomicTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/write_file_atomic_' . uniqid();
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
    public function testWritesContentAndReturnsPath(): void
    {
        $file = $this->tmpDir . '/config.json';

        $this->assertSame( $file , writeFileAtomic( $file , '{"a":1}' ) );
        $this->assertStringEqualsFile( $file , '{"a":1}' );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testCreatesMissingParentDirectory(): void
    {
        $file = $this->tmpDir . '/nested/deep/data.txt';

        writeFileAtomic( $file , 'payload' );
        $this->assertFileExists( $file );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testOverwritesAnExistingFile(): void
    {
        $file = $this->tmpDir . '/data.txt';
        file_put_contents( $file , 'old' );

        writeFileAtomic( $file , 'new' );
        $this->assertStringEqualsFile( $file , 'new' );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testAppliesCustomPermissions(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' );
        }

        $file = $this->tmpDir . '/secret.txt';

        writeFileAtomic( $file , 'data' , 0600 );
        $this->assertSame( 0600 , fileperms( $file ) & 0777 );
    }

    /**
     * @throws FileException
     * @throws DirectoryException
     */
    public function testLeavesNoTemporaryFileBehind(): void
    {
        $file = $this->tmpDir . '/clean.txt';

        writeFileAtomic( $file , 'data' );

        $entries = array_values( array_diff( scandir( $this->tmpDir ) , [ '.' , '..' ] ) );
        $this->assertSame( [ 'clean.txt' ] , $entries );
    }

    public function testThrowsWhenTemporaryWriteFails(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' );
        }

        $readOnly = $this->tmpDir . '/readonly';
        mkdir( $readOnly , 0555 );

        try
        {
            $this->expectException( FileException::class );
            $this->expectExceptionMessage( 'temporary file' );
            writeFileAtomic( $readOnly . '/file.txt' , 'data' );
        }
        finally
        {
            @chmod( $readOnly , 0755 );
        }
    }
}
