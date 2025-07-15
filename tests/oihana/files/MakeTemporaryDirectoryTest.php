<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class MakeTemporaryDirectoryTest extends TestCase
{
    private string $baseTemp;
    private vfsStreamDirectory $root;

    /**
     * Met en place un répertoire temporaire de base sur le vrai système de fichiers
     * et un système de fichiers virtuel pour les tests de permissions.
     */
    protected function setUp(): void
    {
        $this->baseTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana_test_' . uniqid();
        $this->root = vfsStream::setup('temp' ) ;
    }

    /**
     * Nettoie le répertoire temporaire de base après chaque test.
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        if ( is_dir($this->baseTemp ) )
        {
            deleteDirectory( $this->baseTemp , assertable: false );
        }
    }

    /**
     * Teste la création réussie d'un nouveau répertoire.
     * @throws DirectoryException
     */
    public function testCreatesDirectorySuccessfully(): void
    {
        $dirPath = $this->baseTemp . DIRECTORY_SEPARATOR . 'new_dir';

        $this->assertDirectoryDoesNotExist( $dirPath ) ;

        $result = makeTemporaryDirectory( $dirPath ) ;

        $this->assertDirectoryExists( $result );
        $this->assertEquals( $dirPath , $result );
    }

    /**
     * Teste que la fonction ne lève pas d'erreur si le répertoire existe déjà.
     * @throws DirectoryException
     */
    public function testReturnsPathIfDirectoryAlreadyExists(): void
    {
        $dirPath = $this->baseTemp . DIRECTORY_SEPARATOR . 'existing_dir';
        mkdir($dirPath, 0777, true); // Crée le répertoire en amont

        $this->assertDirectoryExists($dirPath);

        $result = makeTemporaryDirectory($dirPath);

        $this->assertDirectoryExists($result);
        $this->assertEquals($dirPath, $result);
    }

    /**
     * Teste la création récursive de répertoires imbriqués.
     * @throws DirectoryException
     */
    public function testCreatesNestedDirectory(): void
    {
        $nestedPath = [$this->baseTemp, 'parent', 'child'];
        $expectedPath = implode(DIRECTORY_SEPARATOR, $nestedPath);

        $this->assertDirectoryDoesNotExist($expectedPath);

        $result = makeTemporaryDirectory($nestedPath);

        $this->assertDirectoryExists($result);
        $this->assertEquals($expectedPath, $result);
    }
}