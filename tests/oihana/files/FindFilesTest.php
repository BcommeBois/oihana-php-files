<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

class FindFilesTest extends TestCase
{
    private string $testDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->testDir = makeDirectory( sys_get_temp_dir() . '/oihana/ListFilesTest_' . uniqid() ) ;

        // Nettoyer avant chaque test
        array_map('unlink', glob($this->testDir . '/*'));

        // Créer des fichiers tests
        file_put_contents($this->testDir . '/foo.php'       , '<?php // foo' ) ;
        file_put_contents($this->testDir . '/bar.blade.php' , 'blade content' ) ;
        file_put_contents($this->testDir . '/test123.php'   , '<?php // test123' ) ;
        file_put_contents($this->testDir . '/.hiddenfile'   , 'hidden' ) ;
        file_put_contents($this->testDir . '/text.txt'      , 'text' ) ;

        makeDirectory  ($this->testDir . '/subdir') ;
        file_put_contents($this->testDir . '/subdir/ignore.php', '<?php // ignore' ) ;
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->testDir  ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithoutFilter()
    {
        $files = findFiles( $this->testDir );

        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);

        // Les fichiers listés : foo.php, bar.blade.php, test123.php (pas les dotfiles ni dossiers)
        $this->assertContains('foo.php', $names);
        $this->assertContains('bar.blade.php', $names);
        $this->assertContains('test123.php', $names);
        $this->assertNotContains('.hiddenfile', $names);
        $this->assertNotContains('ignore.php', $names); // car dans subdir (non récursif)
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithGlobPattern()
    {
        // ------- *.php

        $names = findFiles( $this->testDir, [ 'pattern' => '*.php' ] ) ;
        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $names);

        $this->assertContains('foo.php', $names);
        $this->assertContains('test123.php', $names);
        $this->assertContains('bar.blade.php', $names);
        $this->assertNotContains('text.txt', $names);

        // ------- *.txt

        $names = findFiles( $this->testDir, [ 'pattern' => '*.txt' ] ) ;
        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $names );

        $this->assertNotContains('foo.php', $names);
        $this->assertContains('text.txt', $names);
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithRegexPattern()
    {
        $files = findFiles($this->testDir, [ 'pattern' =>'/^test\d+\.php$/i' ] );
        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);
        $this->assertEquals(['test123.php'], $names);
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithMixedPatterns()
    {
        $patterns = ['*.php', '/^bar.*\.blade\.php$/i'];
        $files =  findFiles($this->testDir, [ 'pattern' => $patterns ] );
        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);

        $this->assertContains('foo.php', $names);
        $this->assertContains('test123.php', $names);
        $this->assertContains('bar.blade.php', $names);
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithMapper()
    {
        $files = findFiles($this->testDir, [ 'pattern' => '*.php' , 'filter' => fn( SplFileInfo $f) => $f->getFilename() ] );
        $this->assertContains('foo.php', $files);
        $this->assertContains('test123.php', $files);
        $this->assertContains('bar.blade.php', $files);
    }

    public function testFindFilesThrowsOnInvalidDirectory()
    {
        $this->expectException(DirectoryException::class);
        findFiles('/path/to/invalid/dir' ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesRecursive()
    {
        $files = findFiles($this->testDir, ['recursive' => true]);
        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);

        $this->assertContains('foo.php', $names);
        $this->assertContains('ignore.php', $names); // Dans subdir, car récursif
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesIncludeDotFiles()
    {
        $files = findFiles($this->testDir, ['includeDots' => true]);
        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);

        $this->assertContains('.hiddenfile', $names);
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithFollowLinks()
    {
        // Créer un lien symbolique dans le temp dir
        $link = $this->testDir . '/link_to_subdir';
        if (!file_exists($link)) {
            symlink($this->testDir . '/subdir', $link);
        }

        $files = findFiles($this->testDir, ['recursive' => true, 'followLinks' => true]);
        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);

        $this->assertContains('ignore.php', $names);

        // Nettoyage du lien après test
        unlink($link);
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesSorting()
    {
        $filesByName = findFiles($this->testDir, ['sort' => 'name']);
        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $filesByName);
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);

        $filesByMTime = findFiles($this->testDir, ['sort' => 'mtime']);
        $namesByMTime = array_map(fn(SplFileInfo $f) => $f->getFilename(), $filesByMTime);
        $this->assertCount(count($names), $namesByMTime); // Simple vérification, difficile de prévoir ordre exact sans sleep
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithCallbackMapping()
    {
        $files = findFiles($this->testDir, [
            'pattern' => '*.php',
            'filter'  => fn(SplFileInfo $f) => strtoupper($f->getFilename()),
        ]);
        $this->assertContains('FOO.PHP', $files);
        $this->assertContains('TEST123.PHP', $files);
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithMultiplePatterns()
    {
        $patterns = ['*.php', '*.txt'];
        $files = findFiles($this->testDir, ['pattern' => $patterns ] );
        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);

        $this->assertContains('foo.php', $names);
        $this->assertContains('text.txt', $names);
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithTypeFiles()
    {
        // Par défaut, type = 'files'
        $files = findFiles($this->testDir, ['mode' => 'files']);
        $this->assertNotEmpty($files);
        foreach ($files as $file)
        {
            $this->assertTrue($file->isFile());
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithTypeDirs()
    {
        $dirs = findFiles($this->testDir, ['mode' => 'dirs']);
        $this->assertNotEmpty($dirs);
        foreach ( $dirs as $dir )
        {
            $this->assertTrue($dir->isDir());
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testFindFilesWithTypeBoth()
    {
        $items = findFiles($this->testDir, ['mode' => 'both']);
        $this->assertNotEmpty( $items );

        $hasFile = false;
        $hasDir = false;
        foreach ($items as $item)
        {
            if ($item->isFile()) {
                $hasFile = true;
            }
            if ($item->isDir()) {
                $hasDir = true;
            }
        }
        $this->assertTrue($hasFile, 'Should find at least one file');
        $this->assertTrue($hasDir, 'Should find at least one directory');
    }
}