<?php

namespace oihana\files ;

use oihana\enums\Char;
use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;

final class GetDirectoryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana_files_' . uniqid();
        mkdir($this->tmpDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if ( is_dir($this->tmpDir ) )
        {
            @chmod($this->tmpDir, 0o777);
            @rmdir($this->tmpDir);
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testReturnsNormalizedPathWithoutTrailingSeparator(): void
    {
        $withTrailing = $this->tmpDir . DIRECTORY_SEPARATOR;
        $result       = getDirectory($withTrailing);
        $this->assertSame($this->tmpDir, $result, 'Le séparateur de fin doit être supprimé.');
    }

    /**
     * @throws DirectoryException
     */
    public function testReturnsSamePathWhenAlreadyNormalized(): void
    {
        $result = getDirectory($this->tmpDir);
        $this->assertSame($this->tmpDir, $result, 'Un chemin déjà normalisé doit être retourné tel quel.');
    }

    public function testThrowsExceptionWhenDirectoryDoesNotExist(): void
    {
        $this->expectException(DirectoryException::class);
        getDirectory($this->tmpDir . '_missing');
    }

    public function testThrowsExceptionWhenDirectoryIsNotReadable(): void
    {
        // Retire les droits de lecture pour provoquer l’erreur
        chmod($this->tmpDir, 0o222);

        $this->expectException(DirectoryException::class);
        getDirectory( $this->tmpDir );
    }

    /**
     * @throws DirectoryException
     */
    public function testArrayInputComposesPathCorrectly(): void
    {
        $segments = [$this->tmpDir, 'sub', '', Char::EMPTY, 'child'];
        $expected = $this->tmpDir . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'child';

        // Crée réellement le sous‑répertoire pour que l’assertion passe
        mkdir($expected, 0o777, true);

        $result = getDirectory($segments);
        $this->assertSame($expected, $result);
    }

    /**
     * @throws DirectoryException
     */
    public function testAssertableFalseAllowsNonExistentDirectory(): void
    {
        $nonExisting = $this->tmpDir . '_ghost';

        // Ne doit pas lancer d’exception
        $path = getDirectory($nonExisting, assertable: false);
        $this->assertSame(rtrim($nonExisting, DIRECTORY_SEPARATOR), $path);
    }

    /**
     * @throws DirectoryException
     */
    public function testNullOrEmptyPathReturnsEmptyStringWhenNotAssertable(): void
    {
        $this->assertSame('', getDirectory(null, assertable: false));
        $this->assertSame('', getDirectory('',  assertable: false));
    }
}