<?php

namespace tests\oihana\files;

use FilesystemIterator;
use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function oihana\files\copyFilteredFiles;
use function oihana\files\deleteDirectory;

class CopyFilteredFilesTest extends TestCase
{
    private string $sourceDir;
    private string $destDir;
    private string $outsideDir;

    protected function setUp(): void
    {
        $this->sourceDir  = sys_get_temp_dir() . '/source_' . uniqid();
        $this->destDir    = sys_get_temp_dir() . '/dest_' . uniqid();
        $this->outsideDir = sys_get_temp_dir() . '/outside_' . uniqid();

        mkdir($this->sourceDir, 0777, true);
        mkdir($this->destDir, 0777, true);
        mkdir($this->outsideDir, 0777, true);

        file_put_contents($this->sourceDir . '/file1.txt', 'file 1');
        file_put_contents($this->sourceDir . '/file2.log', 'file 2');

        mkdir($this->sourceDir . '/subdir');
        file_put_contents($this->sourceDir . '/subdir/file3.txt', 'file 3');
    }

    /**
     * @return void
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory($this->sourceDir);
        deleteDirectory($this->destDir);
        deleteDirectory($this->outsideDir);
    }

    /**
     * @throws DirectoryException
     */
    public function testCopyAllFiles(): void
    {
        $result = copyFilteredFiles($this->sourceDir, $this->destDir, [], null);

        $this->assertTrue($result);
        $this->assertFileExists($this->destDir . '/file1.txt');
        $this->assertFileExists($this->destDir . '/file2.log');
        $this->assertFileExists($this->destDir . '/subdir/file3.txt');
    }

    /**
     * @throws DirectoryException
     */
    public function testCopyWithExcludePattern(): void
    {
        $result = copyFilteredFiles($this->sourceDir, $this->destDir, ['~\.log$~'], null);

        $this->assertTrue($result);
        $this->assertFileExists($this->destDir . '/file1.txt');
        $this->assertFileDoesNotExist($this->destDir . '/file2.log');
        $this->assertFileExists($this->destDir . '/subdir/file3.txt');
    }

    /**
     * @throws DirectoryException
     */
    public function testCopyWithFilterCallback(): void
    {
        $callback = fn(string $path) => str_ends_with($path, '.txt');

        $result = copyFilteredFiles($this->sourceDir, $this->destDir, [], $callback);

        $this->assertTrue($result);
        $this->assertFileExists($this->destDir . '/file1.txt');
        $this->assertFileDoesNotExist($this->destDir . '/file2.log');
        $this->assertFileExists($this->destDir . '/subdir/file3.txt');
    }

    /**
     * @throws DirectoryException
     */
    public function testCopyWithAllFilesExcluded(): void
    {
        $result = copyFilteredFiles($this->sourceDir, $this->destDir, ['~.*~'], null);

        $this->assertFalse($result);
        $this->assertDirectoryExists($this->destDir);
        $this->assertFileDoesNotExist($this->destDir . '/file1.txt');
    }

    /**
     * @throws DirectoryException
     */
    public function testSkipsBrokenSymlink(): void
    {
        // A dangling symlink yields SplFileInfo::getRealPath() === false -> the entry is skipped.
        $link = $this->sourceDir . '/broken.link';
        symlink($this->sourceDir . '/no-such-target', $link);

        $result = copyFilteredFiles($this->sourceDir, $this->destDir, [], null);

        $this->assertTrue($result);
        $this->assertFileExists($this->destDir . '/file1.txt');
        $this->assertFileDoesNotExist($this->destDir . '/broken.link');
    }

    public function testThrowsWhenAFileCannotBeCopied(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Permission tests are not reliable on Windows.' ) ;
        }

        // Unchecked, copy() returning false left the function reporting true for a destination
        // missing one of its files.

        chmod( $this->sourceDir . '/file1.txt' , 0000 ) ;

        try
        {
            $this->expectException( DirectoryException::class ) ;
            $this->expectExceptionMessageMatches( '/Failed to copy ".+file1\.txt" to /' ) ;

            copyFilteredFiles( $this->sourceDir , $this->destDir ) ;
        }
        finally
        {
            chmod( $this->sourceDir . '/file1.txt' , 0644 ) ;
        }
    }

    /**
     * @throws DirectoryException
     */
    public function testFollowLinksCopiesWhatALinkedDirectoryHolds(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        mkdir( $this->outsideDir . '/uploads' ) ;
        file_put_contents( $this->outsideDir . '/uploads/photo.jpg' , 'photo' ) ;
        symlink( $this->outsideDir . '/uploads' , $this->sourceDir . '/linked' ) ;

        $this->assertTrue( copyFilteredFiles( $this->sourceDir , $this->destDir , followLinks: true ) ) ;

        $this->assertFalse( is_link( $this->destDir . '/linked' ) ) ;
        $this->assertDirectoryExists( $this->destDir . '/linked' ) ;
        $this->assertSame( 'photo' , file_get_contents( $this->destDir . '/linked/photo.jpg' ) ) ;

        $this->assertSame
        (
            [ 'file1.txt' , 'file2.log' , 'linked' , 'linked/photo.jpg' , 'subdir' , 'subdir/file3.txt' ] ,
            $this->destinationEntries()
        ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testFollowLinksCopiesWhatALinkedFileHolds(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        file_put_contents( $this->outsideDir . '/data.txt' , 'outside content' ) ;
        symlink( $this->outsideDir . '/data.txt' , $this->sourceDir . '/linked.txt' ) ;

        $this->assertTrue( copyFilteredFiles( $this->sourceDir , $this->destDir , followLinks: true ) ) ;

        $this->assertFalse( is_link( $this->destDir . '/linked.txt' ) ) ;
        $this->assertSame( 'outside content' , file_get_contents( $this->destDir . '/linked.txt' ) ) ;
    }

    public function testFollowLinksRefusesALinkPointingAtADirectoryThatHoldsIt(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // 'deep/inner/up' points at 'deep', which holds it: reading it would walk deep/inner/up/
        // inner/up/inner... without end. The target is not an ancestor of the source root, so a
        // guard comparing it to the root alone would let this one through.

        mkdir( $this->sourceDir . '/deep/inner' , 0777 , true ) ;
        $link = $this->sourceDir . '/deep/inner/up' ;
        symlink( $this->sourceDir . '/deep' , $link ) ;

        $this->expectException( DirectoryException::class ) ;
        $this->expectExceptionMessage( sprintf
        (
            'The symbolic link "%s" points at "%s", which holds it: reading it would not end.' ,
            $link ,
            realpath( $this->sourceDir . '/deep' )
        ) ) ;

        copyFilteredFiles( $this->sourceDir , $this->destDir , followLinks: true ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testFollowLinksSkipsABrokenLink(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        symlink( $this->sourceDir . '/no-such-target' , $this->sourceDir . '/broken.link' ) ;

        $this->assertTrue( copyFilteredFiles( $this->sourceDir , $this->destDir , followLinks: true ) ) ;

        $this->assertFalse( is_link( $this->destDir . '/broken.link' ) ) ;
        $this->assertSame
        (
            [ 'file1.txt' , 'file2.log' , 'subdir' , 'subdir/file3.txt' ] ,
            $this->destinationEntries()
        ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testReproducesALinkToADirectoryWithoutReadingIt(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        mkdir( $this->outsideDir . '/uploads' ) ;
        file_put_contents( $this->outsideDir . '/uploads/photo.jpg' , 'photo' ) ;
        symlink( $this->outsideDir . '/uploads' , $this->sourceDir . '/linked' ) ;

        $this->assertTrue( copyFilteredFiles( $this->sourceDir , $this->destDir ) ) ;

        $this->assertTrue( is_link( $this->destDir . '/linked' ) ) ;
        $this->assertSame( $this->outsideDir . '/uploads' , readlink( $this->destDir . '/linked' ) ) ;

        // The whole destination, listed: a relative path cut from the target's absolute path shows
        // up here as a tree of empty directories named after the filesystem of the host.

        $this->assertSame
        (
            [ 'file1.txt' , 'file2.log' , 'linked' , 'subdir' , 'subdir/file3.txt' ] ,
            $this->destinationEntries()
        ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testReproducesALinkToAFileWithoutReadingIt(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        file_put_contents( $this->outsideDir . '/data.txt' , 'outside content' ) ;
        symlink( $this->outsideDir . '/data.txt' , $this->sourceDir . '/linked.txt' ) ;

        $this->assertTrue( copyFilteredFiles( $this->sourceDir , $this->destDir ) ) ;

        $this->assertTrue( is_link( $this->destDir . '/linked.txt' ) ) ;
        $this->assertSame( $this->outsideDir . '/data.txt' , readlink( $this->destDir . '/linked.txt' ) ) ;

        $this->assertSame
        (
            [ 'file1.txt' , 'file2.log' , 'linked.txt' , 'subdir' , 'subdir/file3.txt' ] ,
            $this->destinationEntries()
        ) ;
    }

    /**
     * @throws DirectoryException
     */
    public function testReproducesARelativeLinkAsRelative(): void
    {
        $this->skipWhenSymlinksAreUnreliable() ;

        // The target is kept as it was written, so a link that pointed inside the source still
        // points inside the copy, at the copy's own file.

        symlink( 'subdir/file3.txt' , $this->sourceDir . '/relative.txt' ) ;

        $this->assertTrue( copyFilteredFiles( $this->sourceDir , $this->destDir ) ) ;

        $this->assertSame( 'subdir/file3.txt' , readlink( $this->destDir . '/relative.txt' ) ) ;
        $this->assertSame( 'file 3' , file_get_contents( $this->destDir . '/relative.txt' ) ) ;
    }

    /**
     * Every entry of the destination, as a path relative to it, sorted. A link is listed as it
     * stands and never walked into.
     *
     * @return string[]
     */
    private function destinationEntries(): array
    {
        $iterator = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator( $this->destDir , FilesystemIterator::SKIP_DOTS ) ,
            RecursiveIteratorIterator::SELF_FIRST
        ) ;

        $offset  = strlen( $this->destDir ) + 1 ;
        $entries = [] ;

        foreach ( $iterator as $entry )
        {
            $entries[] = substr( $entry->getPathname() , $offset ) ;
        }

        sort( $entries ) ;

        return $entries ;
    }

    private function skipWhenSymlinksAreUnreliable(): void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' )
        {
            $this->markTestSkipped( 'Symbolic link tests are not reliable on Windows.' ) ;
        }
    }
}
