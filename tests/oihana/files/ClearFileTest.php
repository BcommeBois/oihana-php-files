<?php

namespace tests\oihana\files ;

use oihana\files\exceptions\FileException;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

use PHPUnit\Framework\TestCase;
use function oihana\files\clearFile;

class ClearFileTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('root', null,
        [
            'test.txt' => "Ligne 1\nLigne 2\n",
            'readonly.txt' => "Do not touch",
        ]);

        $this->root->getChild('readonly.txt')->chmod(0444);
    }

    /**
     * @throws FileException
     */
    public function testClearExistingFile(): void
    {
        $file = $this->root->getChild('test.txt')->url();

        $this->assertNotEmpty(file_get_contents($file));

        // Appelle la fonction pour vider le fichier
        clearFile($file);

        // Vérifie que le fichier est maintenant vide
        $this->assertSame('', file_get_contents($file));
    }

    /**
     * @throws FileException
     */
    public function testClearEmptyFile(): void
    {
        $emptyFile = vfsStream::newFile('empty.txt')->at($this->root)->setContent('');
        $file = $emptyFile->url();

        clearFile($file);

        $this->assertSame('', file_get_contents($file));
    }

    public function testClearNonExistentFileThrows(): void
    {
        $this->expectException(FileException::class);
        clearFile($this->root->url() . '/does_not_exist.txt');
    }

    public function testClearReadOnlyFileThrows(): void
    {
        $file = $this->root->getChild('readonly.txt')->url();
        $this->expectException(FileException::class);
        clearFile($file);
    }
}