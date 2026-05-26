<?php

namespace tests\oihana\files\archive\tar;

use PharData;
use RuntimeException;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use function oihana\files\archive\tar\untar;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

class UntarTest extends TestCase
{
    private string $tarFile;
    private string $outputDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana-php-files/tests/files/archive/untar-tests-' . uniqid() . DIRECTORY_SEPARATOR ;

        makeDirectory( $this->outputDir ) ;

        // Create a tar file for testing

        $tmpFile= $this->outputDir . 'untar_' . uniqid() ;

        $this->tarFile = $tmpFile . '.tar';

        $phar = new PharData( $this->tarFile );

        $sampleFile = $this->outputDir . 'hello.txt';
        file_put_contents( $sampleFile , 'Hello world') ;
        $phar->addFile( $sampleFile , 'hello.txt');
        @unlink($sampleFile )  ;
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->outputDir );
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testUntarBasicExtraction(): void
    {
        $extractDir = $this->outputDir . '/extracted';

        $result = untar( $this->tarFile , $extractDir );

        $this->assertTrue($result);
        $this->assertFileExists($extractDir . '/hello.txt' ) ;
        $this->assertSame('Hello world', file_get_contents($extractDir . '/hello.txt'));
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testUntarWithOverwriteFalse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/path already exists|failed/i');

        $extractDir = $this->outputDir . '/extract_overwrite';

        mkdir( $extractDir, 0777, true);
        file_put_contents($extractDir . '/hello.txt', 'Existing content' ) ;

        // Failed with overwrite = false and a file already exist
        untar($this->tarFile, $extractDir, [ 'overwrite' => false ] ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testUntarWithInvalidTarThrowsException(): void
    {
        $this->expectException(RuntimeException::class);

        $fakeTar = $this->outputDir . '/invalid.tar';
        file_put_contents($fakeTar, 'not a real tar content');

        untar($fakeTar, $this->outputDir . '/fail');
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testUntarMaxExtractedSizeNullKeepsLegacyBehaviour(): void
    {
        $extractDir = $this->outputDir . '/extract_no_limit';

        $result = untar( $this->tarFile , $extractDir , [ 'maxExtractedSize' => null ] ) ;

        $this->assertTrue( $result ) ;
        $this->assertFileExists( $extractDir . '/hello.txt' ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testUntarMaxExtractedSizeBelowLimitAllowsExtraction(): void
    {
        $extractDir = $this->outputDir . '/extract_under_limit';

        // 'Hello world' is 11 bytes; allow up to 1 KiB
        $result = untar( $this->tarFile , $extractDir , [ 'maxExtractedSize' => 1024 ] ) ;

        $this->assertTrue( $result ) ;
        $this->assertFileExists( $extractDir . '/hello.txt' ) ;
        $this->assertSame( 'Hello world' , file_get_contents( $extractDir . '/hello.txt' ) ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testUntarMaxExtractedSizeExceededThrowsAndDoesNotWrite(): void
    {
        $extractDir = $this->outputDir . '/extract_bomb';

        $thrown = false ;
        try
        {
            // 'Hello world' is 11 bytes; cap at 5 → must abort.
            untar( $this->tarFile , $extractDir , [ 'maxExtractedSize' => 5 ] ) ;
        }
        catch ( RuntimeException $e )
        {
            $thrown = true ;
            $this->assertMatchesRegularExpression( '/decompression bomb|exceeds maximum/i' , $e->getMessage() ) ;
        }

        $this->assertTrue( $thrown , 'untar() must throw when total size exceeds maxExtractedSize.' ) ;
        $this->assertFileDoesNotExist( $extractDir . '/hello.txt' , 'No file must be written when the size cap is exceeded.' ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testUntarMaxExtractedSizeExceededInDryRun(): void
    {
        $extractDir = $this->outputDir . '/extract_bomb_dryrun';

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/decompression bomb|exceeds maximum/i' ) ;

        untar( $this->tarFile , $extractDir , [ 'dryRun' => true , 'maxExtractedSize' => 5 ] ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testUntarMaxExtractedSizeZeroRejectsAnyContent(): void
    {
        $extractDir = $this->outputDir . '/extract_zero_limit';

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/decompression bomb|exceeds maximum/i' ) ;

        untar( $this->tarFile , $extractDir , [ 'maxExtractedSize' => 0 ] ) ;
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testUntarWithKeepPermissions(): void
    {
        if ( DIRECTORY_SEPARATOR === '\\' )
        {
            $this->markTestSkipped( 'Permission bits are not fully supported on Windows.' ) ;
        }

        $extractDir = $this->outputDir . '/extract_permissions';

        // Create a file with custom permissions
        $originalFile = $this->outputDir . '/secret.sh';
        file_put_contents($originalFile, '#!/bin/bash\necho "secret"');
        chmod($originalFile, 0755); // Exécutable

        // Create the .tar archive
        $tarFile = $this->outputDir . '/custom_perm.tar';
        $phar = new \PharData($tarFile);
        $phar->addFile($originalFile, 'secret.sh');

        // Change the archive permissions
        $phar['secret.sh']->chmod(0755);

        // Extraction with keepPermissions => true
        untar($tarFile, $extractDir, ['keepPermissions' => true]);

        $extractedFile = $extractDir . '/secret.sh';
        $this->assertFileExists($extractedFile);

        // Check extracted permissions
        $perms = fileperms($extractedFile) & 0777;
        $this->assertSame(0755, $perms, 'Permissions should be preserved (0755)');
    }
}