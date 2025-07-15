<?php

namespace oihana\files ;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class RecursiveFilePathsTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/test_recursive_files_' . uniqid();
        mkdir($this->tempDir);
        $this->createTestFiles();
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempDir);
    }

    /**
     * Crée une structure de fichiers temporaire pour les tests.
     * Structure:
     * - root/
     * - file1.php
     * - file2.txt
     * - sub1/
     * - file3.php
     * - file4.js
     * - excluded_dir/ (should be excluded by name)
     * - file_in_excluded.php
     * - sub2/
     * - sub3/
     * - file5.php
     * - file6.md
     * - excluded_file.php (should be excluded by name)
     * - AnotherFile.PHP (test case sensitivity)
     */
    private function createTestFiles(): void
    {
        mkdir($this->tempDir . '/sub1');
        mkdir($this->tempDir . '/sub1/excluded_dir');
        mkdir($this->tempDir . '/sub2');
        mkdir($this->tempDir . '/sub2/sub3');

        file_put_contents($this->tempDir . '/file1.php', '<?php echo "test";');
        file_put_contents($this->tempDir . '/file2.txt', 'some text');
        file_put_contents($this->tempDir . '/AnotherFile.PHP', '<?php echo "another test";');
        file_put_contents($this->tempDir . '/excluded_file.php', '<?php echo "excluded";');
        file_put_contents($this->tempDir . '/sub1/file3.php', '<?php echo "sub1 test";');
        file_put_contents($this->tempDir . '/sub1/file4.js', 'console.log("js");');
        file_put_contents($this->tempDir . '/sub1/excluded_dir/file_in_excluded.php', '<?php echo "in excluded dir";');
        file_put_contents($this->tempDir . '/sub2/sub3/file5.php', '<?php echo "sub3 test";');
        file_put_contents($this->tempDir . '/sub2/sub3/file6.md', '# Markdown');
    }

    /**
     * Supprime récursivement un répertoire et son contenu.
     * @param string $dir
     */
    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeTempDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    // --- Tests de base ---

    public function testFindAllFilesDefaultOptions(): void // Renamed for clarity
    {
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/file1.php',
            $this->tempDir . '/file2.txt',
            $this->tempDir . '/excluded_file.php',
            $this->tempDir . '/sub1/file3.php',
            $this->tempDir . '/sub1/file4.js',
            $this->tempDir . '/sub1/excluded_dir/file_in_excluded.php',
            $this->tempDir . '/sub2/sub3/file5.php',
            $this->tempDir . '/sub2/sub3/file6.md',
        ];
        sort($expected); // The function sorts by default
        $result = recursiveFilePaths($this->tempDir);
        $this->assertEquals($expected, $result);
    }

    public function testFilterBySpecificExtension(): void
    {
        $options = ['extensions' => ['php']]; // Now explicitly asking for PHP files
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/file1.php',
            $this->tempDir . '/excluded_file.php', // Included if not in 'excludes'
            $this->tempDir . '/sub1/file3.php',
            $this->tempDir . '/sub1/excluded_dir/file_in_excluded.php',
            $this->tempDir . '/sub2/sub3/file5.php',
        ];
        sort($expected);
        $result = recursiveFilePaths($this->tempDir, $options);
        $this->assertEquals($expected, $result);
    }

    public function testFilterByMultipleExtensions(): void
    {
        $options = ['extensions' => ['php', 'js']];
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/excluded_file.php', // <-- ADDED THIS LINE
            $this->tempDir . '/file1.php',
            $this->tempDir . '/sub1/file3.php',
            $this->tempDir . '/sub1/file4.js',
            $this->tempDir . '/sub1/excluded_dir/file_in_excluded.php',
            $this->tempDir . '/sub2/sub3/file5.php',
        ];
        sort($expected);
        $result = recursiveFilePaths($this->tempDir, $options);
        $this->assertEquals($expected, $result);
    }

    public function testExcludeFilesByFilename(): void
    {
        $options = [
            'excludes' => ['excluded_file.php', 'file_in_excluded.php'],
            'extensions' => ['php']
        ];
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/file1.php',
            $this->tempDir . '/sub1/file3.php',
            $this->tempDir . '/sub2/sub3/file5.php',
        ];
        sort($expected);
        $result = recursiveFilePaths($this->tempDir, $options);
        $this->assertEquals($expected, $result);
    }

    public function testExcludeDirectoriesByFilename(): void
    {
        $options = [
            'excludes' => ['excluded_dir'],
            'extensions' => ['php']
        ];
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/excluded_file.php',
            $this->tempDir . '/file1.php',
            $this->tempDir . '/sub1/file3.php',
            $this->tempDir . '/sub2/sub3/file5.php',
        ];
        sort($expected);
        $result = recursiveFilePaths($this->tempDir, $options);
        $this->assertEquals($expected, $result);
    }

    public function testCaseInsensitiveExtensionMatching(): void
    {
        $options = ['extensions' => ['PhP']]; // Teste la casse mixte
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/excluded_file.php',
            $this->tempDir . '/file1.php',
            $this->tempDir . '/sub1/file3.php',
            $this->tempDir . '/sub1/excluded_dir/file_in_excluded.php',
            $this->tempDir . '/sub2/sub3/file5.php',
        ];
        sort($expected);
        $result = recursiveFilePaths($this->tempDir, $options);
        $this->assertEquals($expected, $result);
    }

    public function testNoSorting(): void
    {
        $options = ['sortable' => false];
        $result = recursiveFilePaths($this->tempDir, $options);

        $this->assertCount(9, $result);

        $this->assertContains($this->tempDir . '/file1.php', $result);
        $this->assertContains($this->tempDir . '/file2.txt', $result);
        $this->assertContains($this->tempDir . '/sub2/sub3/file5.php', $result);
        $this->assertContains($this->tempDir . '/sub2/sub3/file6.md', $result);
        $this->assertContains($this->tempDir . '/sub1/file4.js', $result);


        $sortedResult = $result;
        sort($sortedResult);

        $this->assertNotEquals($result, $sortedResult, "The result should not be sorted when 'sortable' is false.");
    }

    // --- Tests de maxDepth ---

    public function testMaxDepth0(): void
    {
        $options = ['maxDepth' => 0, 'extensions' => ['php']];
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/excluded_file.php',
            $this->tempDir . '/file1.php',
        ];
        sort($expected); // Ensure expected is sorted for comparison
        $result = recursiveFilePaths($this->tempDir, $options);
        $this->assertEquals($expected, $result);
    }

    public function testMaxDepth1(): void
    {
        $options = ['maxDepth' => 1, 'extensions' => ['php']];
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/excluded_file.php',
            $this->tempDir . '/file1.php',
            $this->tempDir . '/sub1/file3.php',
        ];
        sort($expected);
        $result = recursiveFilePaths($this->tempDir, $options);
        $this->assertEquals($expected, $result);
    }

    public function testMaxDepthWithSubDirExclusion(): void
    {
        $options = [
            'maxDepth' => 1,
            'extensions' => ['php'],
            'excludes' => ['excluded_dir'] // This excludes the directory itself at depth 1
        ];
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/excluded_file.php',
            $this->tempDir . '/file1.php',
            $this->tempDir . '/sub1/file3.php',
        ];
        sort($expected);
        $result = recursiveFilePaths($this->tempDir, $options);
        $this->assertEquals($expected, $result);
    }

    // --- Tests d'erreur ---

    public function testThrowsExceptionForNonExistentDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^The directory ".*" does not exist or is not a valid directory\.$/');
        recursiveFilePaths('/non/existent/path_' . uniqid());
    }

    public function testThrowsExceptionForInvalidDirectoryPath(): void
    {
        $this->expectException(RuntimeException::class);
        $filePath = $this->tempDir . '/not_a_directory.txt';
        file_put_contents($filePath, 'dummy content');
        $this->expectExceptionMessageMatches('/^The directory ".*" does not exist or is not a valid directory\.$/');
        recursiveFilePaths($filePath);
    }

    public function testEmptyExtensionsArrayIncludesAllFiles(): void
    {
        $options = ['extensions' => []]; // Tableau d'extensions vide
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/file1.php',
            $this->tempDir . '/file2.txt',
            $this->tempDir . '/excluded_file.php',
            $this->tempDir . '/sub1/file3.php',
            $this->tempDir . '/sub1/file4.js',
            $this->tempDir . '/sub1/excluded_dir/file_in_excluded.php',
            $this->tempDir . '/sub2/sub3/file5.php',
            $this->tempDir . '/sub2/sub3/file6.md',
        ];
        sort($expected);
        $result = recursiveFilePaths($this->tempDir, $options);
        $this->assertEquals($expected, $result);
    }

    // Un test pour s'assurer que si 'extensions' est null, tous les fichiers sont retournés.
    public function testNullExtensionsOptionIncludesAllFiles(): void
    {
        $options = ['extensions' => null]; // Option extensions est null
        $expected = [
            $this->tempDir . '/AnotherFile.PHP',
            $this->tempDir . '/file1.php',
            $this->tempDir . '/file2.txt',
            $this->tempDir . '/excluded_file.php',
            $this->tempDir . '/sub1/file3.php',
            $this->tempDir . '/sub1/file4.js',
            $this->tempDir . '/sub1/excluded_dir/file_in_excluded.php',
            $this->tempDir . '/sub2/sub3/file5.php',
            $this->tempDir . '/sub2/sub3/file6.md',
        ];
        sort($expected);
        $result = recursiveFilePaths($this->tempDir, $options);
        $this->assertEquals($expected, $result);
    }
}
