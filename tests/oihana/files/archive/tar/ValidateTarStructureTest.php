<?php

namespace tests\oihana\files\archive\tar;

use PharData;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\DirectoryException;

use function oihana\files\archive\tar\validateTarStructure;
use function oihana\files\deleteDirectory;

class ValidateTarStructureTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tar_test_' . uniqid();
        mkdir($this->tmpDir);
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory($this->tmpDir);
    }

    private function makeValidTarFile(): string
    {
        $path = $this->tmpDir . '/' . 'valid.tar';

        $phar = new PharData($path);
        $phar->addFromString('file1.txt', 'hello');
        $phar->addFromString('file2.txt', 'world');

        return $path;
    }

    private function makeInvalidTarFile(): string
    {
        $path = $this->tmpDir . '/' . 'invalid.tar';
        file_put_contents($path, 'not a real tar archive');
        return $path;
    }

    public function testValidTarStructure()
    {
        $tarFile = $this->makeValidTarFile();
        $this->assertTrue( validateTarStructure($tarFile), 'Expected valid tar file to return true');
    }

    public function testInvalidTarStructure()
    {
        $fakeTar = $this->makeInvalidTarFile();
        $this->assertFalse( validateTarStructure($fakeTar), 'Expected invalid tar file to return false');
    }

    public function testNonExistentFile()
    {
        $nonexistent = $this->tmpDir . '/missing.tar';
        $this->assertFalse( validateTarStructure($nonexistent), 'Expected missing file to return false');
    }
}