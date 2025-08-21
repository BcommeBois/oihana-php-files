<?php

namespace tests\oihana\files ;

use org\bovigo\vfs\vfsStream;

use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\FileException;
use function oihana\files\countFileLines;

class CountFileLinesTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('files');
    }

    /**
     * @throws FileException
     */
    public function testEmptyFileReturnsZero()
    {
        $file = vfsStream::newFile('empty.txt')->at($this->root);
        $this->assertEquals(0, countFileLines($file->url()));
    }

    /**
     * @throws FileException
     */
    public function testFileWithSingleLine()
    {
        $file = vfsStream::newFile('single.txt')
            ->withContent("Hello World")
            ->at($this->root);

        $this->assertEquals(0, countFileLines($file->url()), "No newline should return 0 lines");

        // Optionally, if you consider a single line as 1
        // $this->assertEquals(1, countFileLines($file->url()));
    }

    /**
     * @throws FileException
     */
    public function testFileWithMultipleLines()
    {
        $content = "Line1\nLine2\nLine3\n";
        $file = vfsStream::newFile('multi.txt')
            ->withContent($content)
            ->at($this->root);

        $this->assertEquals(3, countFileLines($file->url()));
    }

    /**
     * @throws FileException
     */
    public function testFileWithWindowsLineEndings()
    {
        $content = "Line1\r\nLine2\r\nLine3\r\n";
        $file = vfsStream::newFile('windows.txt')
            ->withContent($content)
            ->at($this->root);

        // Substr_count("\n") counts all \n occurrences, works with \r\n
        $this->assertEquals(3, countFileLines($file->url()));
    }

    public function testFileDoesNotExistThrowsException()
    {
        $this->expectException(FileException::class);
        countFileLines($this->root->url() . '/nonexistent.txt');
    }
}