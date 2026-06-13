<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

use function oihana\files\bunzip2File;
use function oihana\files\bzip2File;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

class Bzip2FileTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/bzip2_file_' . uniqid();
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
    public function testBzip2DefaultDestinationAndRoundTrip(): void
    {
        $content = str_repeat( 'the quick brown fox 0123456789 ' , 200 );
        $source  = $this->makeFile( 'data.txt' , $content );

        $bz = bzip2File( $source );
        $this->assertSame( $source . '.bz2' , $bz );
        $this->assertFileExists( $bz );
        $this->assertNotSame( $content , file_get_contents( $bz ) ); // really compressed

        $back = bunzip2File( $bz );
        $this->assertSame( $this->tmpDir . '/data.txt' , $back );
        $this->assertStringEqualsFile( $back , $content );
    }

    /**
     * @throws FileException
     */
    public function testBunzip2FallbackDestinationWithoutBz2Suffix(): void
    {
        $source = $this->makeFile( 'plain.txt' , 'hello world' );
        $packed = bzip2File( $source , $this->tmpDir . '/archive.bin' );

        $out = bunzip2File( $packed ); // no .bz2 suffix -> archive.bin.out
        $this->assertSame( $packed . '.out' , $out );
        $this->assertStringEqualsFile( $out , 'hello world' );
    }

    public function testBzip2ThrowsWhenSourceMissing(): void
    {
        $this->expectException( FileException::class );
        bzip2File( $this->tmpDir . '/missing.txt' );
    }

    public function testBzip2ThrowsWhenDestinationExistsAndNoOverwrite(): void
    {
        $source = $this->makeFile( 'data.txt' , 'x' );
        $dest   = $this->makeFile( 'data.txt.bz2' , 'existing' );

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'already exists' );
        bzip2File( $source , $dest , false );
    }

    public function testBzip2ThrowsWhenDestinationNotWritable(): void
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
            $this->expectExceptionMessage( 'bzip2 destination' );
            bzip2File( $source , $readOnly . '/out.bz2' );
        }
        finally
        {
            @chmod( $readOnly , 0755 );
        }
    }

    public function testBunzip2ThrowsWhenSourceMissing(): void
    {
        $this->expectException( FileException::class );
        bunzip2File( $this->tmpDir . '/missing.bz2' );
    }

    /**
     * @throws FileException
     */
    public function testBunzip2ThrowsWhenDestinationExistsAndNoOverwrite(): void
    {
        $source = $this->makeFile( 'data.txt' , 'payload' );
        $bz     = bzip2File( $source );
        file_put_contents( $this->tmpDir . '/data.txt' , 'keep me' ); // restore target exists

        $this->expectException( FileException::class );
        $this->expectExceptionMessage( 'already exists' );
        bunzip2File( $bz , null , false );
    }

    public function testBunzip2ThrowsWhenDestinationNotWritable(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' );
        }

        $source = $this->makeFile( 'data.txt' , 'payload' );
        $bz     = bzip2File( $source );

        $readOnly = $this->tmpDir . '/ro';
        mkdir( $readOnly , 0555 );

        try
        {
            $this->expectException( FileException::class );
            $this->expectExceptionMessage( 'destination' );
            bunzip2File( $bz , $readOnly . '/out.txt' );
        }
        finally
        {
            @chmod( $readOnly , 0755 );
        }
    }
}
