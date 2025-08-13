<?php

namespace tests\oihana\files\archive\tar;

use oihana\files\exceptions\FileException;
use PHPUnit\Framework\TestCase;

use function oihana\files\archive\tar\assertTar;

class AssertTarTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/tar_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir))
        {
            $this->deleteDir($this->tempDir);
        }
    }

    private function deleteDir(string $dir): void
    {
        foreach (glob("$dir/*") as $file) {
            is_dir($file) ? $this->deleteDir($file) : unlink($file);
        }
        rmdir($dir);
    }

    public function testThrowsFileExceptionOnMissingFile(): void
    {
        $this->expectException(FileException::class);
        assertTar($this->tempDir . '/missing.tar');
    }

    /**
     * @throws FileException
     */
    public function testReturnsFalseOnInvalidExtension(): void
    {
        $file = $this->tempDir . '/invalid.txt';
        file_put_contents($file, 'not a tar');

        $this->assertFalse( assertTar( $file ) ) ;
    }

    /**
     * @throws FileException
     */
    public function testReturnsFalseOnInvalidMime(): void
    {
        $file = $this->tempDir . '/fake.tar';
        file_put_contents($file, 'not a real tar content');

        $this->assertFalse( assertTar( $file ) ) ;
    }

    /**
     * @throws FileException
     */
    public function testReturnsTrueOnValidTarFile(): void
    {
        $file = $this->tempDir . '/valid.tar';

        // Create minimal valid tar using PharData
        $tar = new \PharData($file);
        file_put_contents($this->tempDir . '/hello.txt', 'hello');
        $tar->addFile($this->tempDir . '/hello.txt', 'hello.txt');

        $this->assertTrue( assertTar( $file ) ) ;
    }

    /**
     * @throws FileException
     */
    public function testStrictModeFailsOnCorruptTar(): void
    {
        $file = $this->tempDir . '/bad.tar';
        file_put_contents($file, 'not a valid tar content');

        $this->assertFalse( assertTar( $file , strictMode: true ) );
    }
}