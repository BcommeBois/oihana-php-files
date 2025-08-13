<?php

namespace tests\oihana\files\phar ;

use oihana\files\exceptions\DirectoryException;
use PharData;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;
use function oihana\files\phar\getPharBasePath;

#[CoversFunction('oihana\files\phar\getPharBasePath')]
class GetPharBasePathTest extends TestCase
{
    private string $tempDir ;
    private string $tempFile ;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tempDir  = sys_get_temp_dir() . '/oihana-php-files/tests/' ;
        $this->tempFile = $this->tempDir . 'test_archive.tar' ;


        makeDirectory( $this->tempDir  ) ;

        if ( file_exists( $this->tempFile ) )
        {
            unlink( $this->tempFile ) ;
        }

        try
        {
            $phar = new PharData( $this->tempFile );
            $phar->addFromString('file.txt', 'test content');
        }
        catch ( UnexpectedValueException $e )
        {
            $this->fail("La préparation du test a échoué : impossible de créer l'archive .tar : " . $e->getMessage());
        }
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tempDir ) ;
    }

    /**
     * Le test est maintenant beaucoup plus simple, car la préparation
     * et le nettoyage sont gérés par setUp() et tearDown().
     */
    public function testReturnsCorrectlyFormattedPathWithoutMock(): void
    {
        // 1. Arrange
        $phar = new PharData($this->tempFile);
        $expectedPath = 'phar://' . realpath( $this->tempFile ) ;

        // 2. Act
        $result = getPharBasePath( $phar );

        // 3. Assert
        $this->assertEquals($expectedPath, $result);
    }
}