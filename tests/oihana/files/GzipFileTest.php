<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\deleteDirectory;
use function oihana\files\gunzipFile;
use function oihana\files\gzipFile;
use function oihana\files\makeDirectory;

class GzipFileTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/gzip_file_' . uniqid();
        makeDirectory( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir );
    }

    private function makeFile( string $name , string $content ): string
    {
        $path = $this->tmpDir . '/' . $name;
        file_put_contents( $path , $content );
        return $path;
    }

    /**
     * @throws FileException
     */
    public function testGzipDefaultDestinationAndRoundTrip(): void
    {
        $content = str_repeat( 'the quick brown fox 0123456789 ' , 200 );
        $source  = $this->makeFile( 'data.txt' , $content );

        $gz = gzipFile( $source );
        $this->assertSame( $source . '.gz' , $gz );
        $this->assertFileExists( $gz );
        $this->assertNotSame( $content , file_get_contents( $gz ) ); // really compressed

        $back = gunzipFile( $gz );
        $this->assertSame( $this->tmpDir . '/data.txt' , $back );
        $this->assertStringEqualsFile( $back , $content );
    }

    /**
     * @throws FileException
     */
    public function testGzipExplicitDestinationAndLevel(): void
    {
        $source = $this->makeFile( 'data.txt' , 'payload' );
        $dest   = $this->tmpDir . '/out.gz';

        gzipFile( $source , $dest , 9 );
        $this->assertStringEqualsFile( gunzipFile( $dest , $this->tmpDir . '/restored.txt' ) , 'payload' );
    }

    /**
     * @throws FileException
     */
    public function testGunzipFallbackDestinationWithoutGzSuffix(): void
    {
        // Make a gzip stream stored under a name that does NOT end in .gz
        $source = $this->makeFile( 'plain.txt' , 'hello world' );
        $packed = gzipFile( $source , $this->tmpDir . '/archive.bin' );

        $out = gunzipFile( $packed ); // no .gz suffix -> archive.bin.out
        $this->assertSame( $packed . '.out' , $out );
        $this->assertStringEqualsFile( $out , 'hello world' );
    }

    public function testGzipThrowsWhenSourceMissing(): void
    {
        $this->expectException( FileException::class );
        gzipFile( $this->tmpDir . '/missing.txt' );
    }

    public function testGzipThrowsWhenDestinationExistsAndNoOverwrite(): void
    {
        $source = $this->makeFile( 'data.txt' , 'x' );
        $dest   = $this->makeFile( 'data.txt.gz' , 'existing' );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'already exists' );
        gzipFile( $source , $dest , -1 , false );
    }

    public function testGzipThrowsWhenDestinationNotWritable(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' );
        }

        $source   = $this->makeFile( 'data.txt' , 'x' );
        $readOnly = $this->tmpDir . '/ro';
        mkdir( $readOnly , 0555 );

        try
        {
            $this->expectException( FileException::class );
            $this->expectExceptionMessage( 'gzip destination' );
            gzipFile( $source , $readOnly . '/out.gz' );
        }
        finally
        {
            @chmod( $readOnly , 0755 );
        }
    }

    public function testGunzipThrowsWhenSourceMissing(): void
    {
        $this->expectException( FileException::class );
        gunzipFile( $this->tmpDir . '/missing.gz' );
    }

    /**
     * @throws FileException
     */
    public function testGunzipThrowsWhenDestinationExistsAndNoOverwrite(): void
    {
        $source = $this->makeFile( 'data.txt' , 'payload' );
        $gz     = gzipFile( $source );
        file_put_contents( $this->tmpDir . '/data.txt' , 'keep me' ); // restore target exists

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'already exists' );
        gunzipFile( $gz , null , false );
    }

    public function testGunzipThrowsWhenDestinationNotWritable(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' );
        }

        $source = $this->makeFile( 'data.txt' , 'payload' );
        $gz     = gzipFile( $source );

        $readOnly = $this->tmpDir . '/ro';
        mkdir( $readOnly , 0555 );

        try
        {
            $this->expectException( FileException::class );
            $this->expectExceptionMessage( 'destination' );
            gunzipFile( $gz , $readOnly . '/out.txt' );
        }
        finally
        {
            @chmod( $readOnly , 0755 );
        }
    }
}
