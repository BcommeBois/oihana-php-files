<?php

namespace oihana\files\archive\tar;

use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class UntarTest extends TestCase
{
    private string $tarFile;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/untar_test_' . uniqid();
        mkdir($this->outputDir, 0777, true);

        // Create a tar file for testing
        $tmpFile = tempnam(sys_get_temp_dir(), 'untar_');
        $sampleFile = $this->outputDir . '/hello.txt';
        file_put_contents($sampleFile, 'Hello world');

        $tarFile = $tmpFile . '.tar';
        $phar = new \PharData($tarFile);
        $phar->addFile($sampleFile, 'hello.txt');

        $this->tarFile = $tarFile;
    }

    protected function tearDown(): void
    {
        @unlink($this->tarFile);
        $this->deleteDir($this->outputDir);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }

        rmdir($dir);
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testUntarBasicExtraction(): void
    {
        $extractDir = $this->outputDir . '/extracted';

        $result = untar($this->tarFile, $extractDir);

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

        // Doit échouer car overwrite = false et fichier déjà présent
        untar($this->tarFile, $extractDir, ['overwrite' => false]);
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

        // Création d’un fichier avec des permissions personnalisées
        $originalFile = $this->outputDir . '/secret.sh';
        file_put_contents($originalFile, '#!/bin/bash\necho "secret"');
        chmod($originalFile, 0755); // Exécutable

        // Création de l'archive .tar
        $tarFile = $this->outputDir . '/custom_perm.tar';
        $phar = new \PharData($tarFile);
        $phar->addFile($originalFile, 'secret.sh');

        // On remet les permissions dans l'archive
        $phar['secret.sh']->chmod(0755);

        // Extraction avec l’option keepPermissions => true
        untar($tarFile, $extractDir, ['keepPermissions' => true]);

        $extractedFile = $extractDir . '/secret.sh';
        $this->assertFileExists($extractedFile);

        // On vérifie les permissions extraites
        $perms = fileperms($extractedFile) & 0777;
        $this->assertSame(0755, $perms, 'Permissions should be preserved (0755)');
    }
}