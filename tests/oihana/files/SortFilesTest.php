<?php

namespace tests\oihana\files;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use function oihana\files\deleteDirectory;
use function oihana\files\findFiles;
use function oihana\files\makeDirectory;
use function oihana\files\sortFiles;

class SortFilesTest extends TestCase
{
    private string $testDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->testDir = makeDirectory( sys_get_temp_dir() . '/oihana/SortFilesTest_' . uniqid() ) ;

        // Fichiers de tailles / extensions différentes
        file_put_contents($this->testDir . '/foo.php',          str_repeat('A', 10));   // 10 o
        file_put_contents($this->testDir . '/bar.blade.php',    str_repeat('A', 30));   // 30 o
        file_put_contents($this->testDir . '/test123.php',      str_repeat('A', 20));   // 20 o
        file_put_contents($this->testDir . '/z_last.txt',       str_repeat('A',  5));   // 5 o
        file_put_contents($this->testDir . '/a_first.TXT',      str_repeat('A', 15));   // 15 o
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->testDir  ) ;
    }

    /** Tri simple par nom ASC (sensible à la casse)
     * @throws DirectoryException
     */
    public function testSortByNameAsc(): void
    {
        $files = findFiles($this->testDir, ['includeDots' => false]);
        sortFiles($files, 'name');

        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);
        $this->assertSame(
            ['a_first.TXT', 'bar.blade.php', 'foo.php', 'test123.php', 'z_last.txt'],
            $names
        );
    }

    /** Tri par nom DESC (sensible à la casse)
     * @throws DirectoryException
     */
    public function testSortByNameDesc(): void
    {
        $files = findFiles($this->testDir);
        sortFiles($files, 'name', 'desc');

        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);
        $this->assertSame(
            ['z_last.txt', 'test123.php', 'foo.php', 'bar.blade.php', 'a_first.TXT'],
            $names
        );
    }

    /** Tri CI + multi‑critères : extension puis nom
     * @throws DirectoryException
     */
    public function testSortByExtensionThenName(): void
    {
        $files = findFiles($this->testDir);
        sortFiles($files, ['extension', 'ci_name']);

        $names = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);
        $this->assertSame(
            [ // .blade.php → .php → .txt
                'bar.blade.php',
                'foo.php',
                'test123.php',
                'a_first.TXT',
                'z_last.txt',
            ],
            $names
        );
    }

    /** Custom comparator : taille DESC
     * @throws DirectoryException
     */
    public function testSortBySizeDescCustom(): void
    {
        $files = findFiles($this->testDir);
        sortFiles(
            $files,
            fn(SplFileInfo $a, SplFileInfo $b) => $a->getSize() <=> $b->getSize(),
            'desc'
        );

        $sizes = array_map(fn(SplFileInfo $f) => $f->getSize(), $files);
        $this->assertSame(
            [30, 20, 15, 10, 5],   // tailles triées décroissantes
            $sizes
        );
    }

    /** Tri par clé 'size' (match arm) ASC
     * @throws DirectoryException
     */
    public function testSortBySizeKeyAsc(): void
    {
        $files = findFiles($this->testDir);
        sortFiles($files, 'size');

        $sizes = array_map(fn(SplFileInfo $f) => $f->getSize(), $files);
        $this->assertSame([5, 10, 15, 20, 30], $sizes);
    }

    /** Exerce les bras 'type' / 'atime' / 'ctime' du match.
     * @throws DirectoryException
     */
    public function testSortByTypeAtimeCtimeKeys(): void
    {
        foreach (['type', 'atime', 'ctime'] as $key)
        {
            $files = findFiles($this->testDir);
            sortFiles($files, $key);

            $this->assertCount(5, $files);
            foreach ($files as $f)
            {
                $this->assertSame('file', $f->getType());
            }
        }
    }

    /** Clé inconnue -> bras default (cmp 0) -> ordre d'origine conservé.
     * @throws DirectoryException
     */
    public function testUnknownSortKeyKeepsOriginalOrder(): void
    {
        $files  = findFiles($this->testDir);
        $before = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);

        sortFiles($files, 'totally-unknown-key');

        $after = array_map(fn(SplFileInfo $f) => $f->getFilename(), $files);
        $this->assertSame($before, $after);
    }
}