<?php

namespace oihana\files\archive\tar;

use PharData;
use RuntimeException;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
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