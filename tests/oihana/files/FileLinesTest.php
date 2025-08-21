<?php

namespace tests\oihana\files ;

use org\bovigo\vfs\vfsStream;

use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\FileException;

use function oihana\files\getFileLines;
use function oihana\files\getFileLinesGenerator;

class FileLinesTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup();
    }

    /**
     * @throws FileException
     */
    public function testGetFileLinesGeneratorYieldsLines()
    {
        $content = "line1\nline2\nline3";
        $file = vfsStream::newFile('test.txt')->at($this->root)->setContent($content);

        $lines = iterator_to_array(getFileLinesGenerator($file->url()));
        $this->assertSame(['line1', 'line2', 'line3'], $lines);
    }

    /**
     * @throws FileException
     */
    public function testGetFileLinesGeneratorWithMapping()
    {
        $content = "1\n2\n3";
        $file = vfsStream::newFile('numbers.txt')->at($this->root)->setContent($content);

        $lines = iterator_to_array(getFileLinesGenerator($file->url(), fn($line) => (int)$line * 2));
        $this->assertSame([2, 4, 6], $lines);
    }

    /**
     * @throws FileException
     */
    public function testGetFileLinesReturnsArray()
    {
        $content = "a\nb\nc";
        $file = vfsStream::newFile('letters.txt')->at($this->root)->setContent($content);

        $lines = getFileLines($file->url());
        $this->assertSame(['a', 'b', 'c'], $lines);
    }

    /**
     * @throws FileException
     */
    public function testGetFileLinesEmptyFile()
    {
        $file = vfsStream::newFile('empty.txt')->at($this->root)->setContent('');

        $lines = getFileLines($file->url());
        $this->assertSame([], $lines);
    }

    public function testGetFileLinesGeneratorThrowsOnMissingFile()
    {
        $nonExistentFile = $this->root->url() . '/nofile.txt';

        $this->expectException( FileException::class ) ;

        $generator = getFileLinesGenerator($nonExistentFile);

        // Enforce the evaluation of the generator with the first element
        $generator->current() ;

        // Warning the generator error evaluation not working, use getFileLines !!
    }

    public function testGetFileLinesThrowsOnMissingFile()
    {
        $this->expectException(FileException::class);
        getFileLines($this->root->url() . '/nofile.txt');
    }

    /**
     * @throws FileException
     */
    public function testGetFileLinesWithMapping()
    {
        $content = "1\n2\n3\n4";
        $file = vfsStream::newFile('numbers.txt')->at($this->root)->setContent($content);

        // Mapping function: convertit chaque ligne en int et multiplie par 10
        $mappedLines = getFileLines($file->url(), fn($line) => (int)$line * 10);

        $this->assertSame([10, 20, 30, 40], $mappedLines);
    }
}