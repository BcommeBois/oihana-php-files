<?php

namespace oihana\files\archive;

use oihana\files\exceptions\DirectoryException;
use PharData;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use oihana\files\enums\TarOption;
use oihana\files\exceptions\FileException;

class UntarTest extends TestCase
{
    private string $tempDir;
    private string $testTarFile;
    private string $outputPath;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/untar_test_' . uniqid();
        $this->testTarFile = $this->tempDir . '/test.tar';
        $this->outputPath = $this->tempDir . '/output';

        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     */
    public function testExtractsTarFileSuccessfullyWithDefaultOptions(): void
    {
        // Arrange
        $this->createValidTarFile();

        // Act
        $result = untar($this->testTarFile, $this->outputPath);

        // Assert
        $this->assertTrue($result);
        $this->assertDirectoryExists($this->outputPath);
        $this->assertFileExists($this->outputPath . '/file1.txt');
        $this->assertFileExists($this->outputPath . '/file2.txt');
    }
    //
    // public function testExtractsTarFileWithOverwriteEnabled(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //     $options = [TarOption::OVERWRITE => true];
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath, $options);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     $this->assertFileExists($this->outputPath . '/file1.txt');
    // }
    //
    // public function testExtractsTarFileWithOverwriteDisabled(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //     $options = [TarOption::OVERWRITE => false];
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath, $options);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     $this->assertFileExists($this->outputPath . '/file1.txt');
    // }
    //
    // public function testExtractsTarFileWithKeepPermissionsEnabled(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //     $options = [TarOption::KEEP_PERMISSIONS => true];
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath, $options);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     $this->assertFileExists($this->outputPath . '/file1.txt');
    // }
    //
    // public function testExtractsTarFileWithKeepPermissionsDisabled(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //     $options = [TarOption::KEEP_PERMISSIONS => false];
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath, $options);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     $this->assertFileExists($this->outputPath . '/file1.txt');
    // }
    //
    // public function testExtractsTarFileWithAllOptions(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //     $options = [
    //         TarOption::OVERWRITE => true,
    //         TarOption::KEEP_PERMISSIONS => true
    //     ];
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath, $options);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     $this->assertFileExists($this->outputPath . '/file1.txt');
    //     $this->assertFileExists($this->outputPath . '/file2.txt');
    // }
    //
    // public function testThrowsExceptionWhenTarFileDoesNotExist(): void
    // {
    //     // Arrange
    //     $nonExistentFile = $this->tempDir . '/nonexistent.tar';
    //
    //     // Assert
    //     $this->expectException(FileException::class);
    //
    //     // Act
    //     untar($nonExistentFile, $this->outputPath);
    // }
    //
    // public function testThrowsExceptionWhenTarFileIsInvalid(): void
    // {
    //     // Arrange
    //     $invalidTarFile = $this->tempDir . '/invalid.tar';
    //     file_put_contents($invalidTarFile, 'this is not a valid tar file');
    //
    //     // Assert
    //     $this->expectException(FileException::class);
    //
    //     // Act
    //     untar($invalidTarFile, $this->outputPath);
    // }
    //
    // public function testThrowsRuntimeExceptionWhenExtractionFails(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //
    //     // Create a file in the output path that cannot be overwritten
    //     mkdir($this->outputPath, 0755, true);
    //     $conflictFile = $this->outputPath . '/file1.txt';
    //     file_put_contents($conflictFile, 'existing content');
    //     chmod($conflictFile, 0444); // Make it read-only
    //     chmod($this->outputPath, 0555); // Make directory read-only
    //
    //     // Assert
    //     $this->expectException(RuntimeException::class);
    //     $this->expectExceptionMessage('Failed to extract tar file');
    //
    //     // Act
    //     try {
    //         untar($this->testTarFile, $this->outputPath, [TarOption::OVERWRITE => false]);
    //     } finally {
    //         // Cleanup: restore permissions
    //         chmod($this->outputPath, 0755);
    //         chmod($conflictFile, 0644);
    //     }
    // }
    //
    // public function testRuntimeExceptionContainsProperErrorMessage(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //     $readOnlyOutput = $this->tempDir . '/readonly';
    //     mkdir($readOnlyOutput, 0555, true); // Create read-only directory
    //
    //     // Assert
    //     $this->expectException(RuntimeException::class);
    //     $this->expectExceptionMessage('Failed to extract tar file');
    //
    //     // Act
    //     try {
    //         untar($this->testTarFile, $readOnlyOutput);
    //     } catch (RuntimeException $e) {
    //         $this->assertStringContainsString(json_encode($this->testTarFile, JSON_UNESCAPED_SLASHES), $e->getMessage());
    //         $this->assertStringContainsString(json_encode($readOnlyOutput, JSON_UNESCAPED_SLASHES), $e->getMessage());
    //         throw $e;
    //     } finally {
    //         // Cleanup
    //         chmod($readOnlyOutput, 0755);
    //     }
    // }
    //
    // public function testCreatesOutputDirectoryIfNotExists(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //     $newOutputPath = $this->tempDir . '/new/nested/output';
    //
    //     // Act
    //     $result = untar($this->testTarFile, $newOutputPath);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($newOutputPath);
    //     $this->assertFileExists($newOutputPath . '/file1.txt');
    //     $this->assertFileExists($newOutputPath . '/file2.txt');
    // }
    //
    // public function testHandlesEmptyOptionsArray(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //     $options = [];
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath, $options);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     $this->assertFileExists($this->outputPath . '/file1.txt');
    // }
    //
    // public function testHandlesPartialOptionsArray(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //     $options = [TarOption::OVERWRITE => false];
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath, $options);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     $this->assertFileExists($this->outputPath . '/file1.txt');
    // }
    //
    // public function testExtractsNestedDirectoryStructure(): void
    // {
    //     // Arrange
    //     $this->createTarWithNestedStructure();
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     $this->assertDirectoryExists($this->outputPath . '/subdir');
    //     $this->assertFileExists($this->outputPath . '/file1.txt');
    //     $this->assertFileExists($this->outputPath . '/subdir/file2.txt');
    // }
    //
    // public function testExtractsWithSpecialCharactersInFilenames(): void
    // {
    //     // Arrange
    //     $this->createTarWithSpecialCharacters();
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     $this->assertFileExists($this->outputPath . '/file with spaces.txt');
    //     $this->assertFileExists($this->outputPath . '/file-with-dashes.txt');
    // }
    //
    // public function testOverwriteExistingFiles(): void
    // {
    //     // Arrange
    //     $this->createValidTarFile();
    //
    //     // Create output directory with existing files
    //     mkdir($this->outputPath, 0755, true);
    //     file_put_contents($this->outputPath . '/file1.txt', 'original content');
    //
    //     $options = [TarOption::OVERWRITE => true];
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath, $options);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     $this->assertFileExists($this->outputPath . '/file1.txt');
    //     $this->assertNotEquals('original content', file_get_contents($this->outputPath . '/file1.txt'));
    // }
    //
    // public function testExtractsEmptyTarFile(): void
    // {
    //     // Arrange
    //     $this->createEmptyTarFile();
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //     // Directory should be empty except for . and ..
    //     $files = array_diff(scandir($this->outputPath), ['.', '..']);
    //     $this->assertEmpty($files);
    // }
    //
    // public function testExtractsLargeTarFile(): void
    // {
    //     // Arrange
    //     $this->createLargeTarFile();
    //
    //     // Act
    //     $result = untar($this->testTarFile, $this->outputPath);
    //
    //     // Assert
    //     $this->assertTrue($result);
    //     $this->assertDirectoryExists($this->outputPath);
    //
    //     // Check that all files were extracted
    //     for ($i = 0; $i < 10; $i++) {
    //         $this->assertFileExists($this->outputPath . "/file{$i}.txt");
    //     }
    // }

    /**
     * Helper method to create a valid tar file for testing
     */
    private function createValidTarFile(): void
    {
        $sourceDir = $this->tempDir . '/source';
        mkdir($sourceDir, 0755, true);
        file_put_contents($sourceDir . '/file1.txt', 'content of file 1');
        file_put_contents($sourceDir . '/file2.txt', 'content of file 2');

        $phar = new PharData($this->testTarFile);
        $phar->buildFromDirectory($sourceDir);
    }

    /**
     * Helper method to create a tar file with nested directory structure
     */
    private function createTarWithNestedStructure(): void
    {
        $sourceDir = $this->tempDir . '/source';
        mkdir($sourceDir . '/subdir', 0755, true);
        file_put_contents($sourceDir . '/file1.txt', 'root file content');
        file_put_contents($sourceDir . '/subdir/file2.txt', 'nested file content');

        $phar = new PharData($this->testTarFile);
        $phar->buildFromDirectory($sourceDir);
    }

    /**
     * Helper method to create a tar file with special characters in filenames
     */
    private function createTarWithSpecialCharacters(): void
    {
        $sourceDir = $this->tempDir . '/source';
        mkdir($sourceDir, 0755, true);
        file_put_contents($sourceDir . '/file with spaces.txt', 'content with spaces');
        file_put_contents($sourceDir . '/file-with-dashes.txt', 'content with dashes');

        $phar = new PharData($this->testTarFile);
        $phar->buildFromDirectory($sourceDir);
    }

    /**
     * Helper method to create an empty tar file
     */
    private function createEmptyTarFile(): void
    {
        $sourceDir = $this->tempDir . '/empty_source';
        mkdir($sourceDir, 0755, true);

        $phar = new PharData($this->testTarFile);
        $phar->buildFromDirectory($sourceDir);
    }

    /**
     * Helper method to create a tar file with multiple files
     */
    private function createLargeTarFile(): void
    {
        $sourceDir = $this->tempDir . '/large_source';
        mkdir($sourceDir, 0755, true);

        for ($i = 0; $i < 10; $i++) {
            file_put_contents($sourceDir . "/file{$i}.txt", "content of file {$i}");
        }

        $phar = new PharData($this->testTarFile);
        $phar->buildFromDirectory($sourceDir);
    }

    /**
     * Helper method to recursively remove a directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                chmod($path, 0644); // Ensure file is writable before deletion
                unlink($path);
            }
        }
        chmod($dir, 0755); // Ensure directory is writable before deletion
        rmdir($dir);
    }
}