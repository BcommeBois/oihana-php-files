<?php

namespace tests\oihana\files\archive\zip;

use ZipArchive;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;

use function oihana\files\archive\zip\validateZipStructure;
use function oihana\files\deleteDirectory;

class ValidateZipStructureTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/zip_test_' . uniqid();
        mkdir( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir );
    }

    private function makeValidZipFile(): string
    {
        $path = $this->tmpDir . '/valid.zip';
        $zip  = new ZipArchive();
        $zip->open( $path , ZipArchive::CREATE );
        $zip->addFromString( 'file1.txt' , 'hello' );
        $zip->addFromString( 'file2.txt' , 'world' );
        $zip->close();
        return $path;
    }

    private function makeInvalidZipFile(): string
    {
        $path = $this->tmpDir . '/invalid.zip';
        file_put_contents( $path , 'not a real zip archive' );
        return $path;
    }

    private function makeZipWithManyFiles( int $count ): string
    {
        $path = $this->tmpDir . '/many.zip';
        $zip  = new ZipArchive();
        $zip->open( $path , ZipArchive::CREATE );
        for ( $i = 0 ; $i < $count ; $i++ )
        {
            $zip->addFromString( "file{$i}.txt" , "content {$i}" );
        }
        $zip->close();
        return $path;
    }

    public function testValidZipStructure(): void
    {
        $this->assertTrue( validateZipStructure( $this->makeValidZipFile() ) , 'Expected valid zip file to return true' );
    }

    public function testInvalidZipStructure(): void
    {
        $this->assertFalse( validateZipStructure( $this->makeInvalidZipFile() ) , 'Expected invalid zip file to return false' );
    }

    public function testNonExistentFile(): void
    {
        $this->assertFalse( validateZipStructure( $this->tmpDir . '/missing.zip' ) , 'Expected missing file to return false' );
    }

    public function testStopsAfterTenFiles(): void
    {
        // More than 10 entries exercises the min()/break guard in the inspection loop.
        $this->assertTrue( validateZipStructure( $this->makeZipWithManyFiles( 15 ) ) , 'Expected a large valid zip file to return true' );
    }
}
