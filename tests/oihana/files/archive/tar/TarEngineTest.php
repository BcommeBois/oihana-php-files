<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use oihana\files\enums\CompressionType;

use function oihana\files\archive\tar\tar;
use function oihana\files\archive\tar\tarBinary;
use function oihana\files\archive\tar\tarBinaryHandles;
use function oihana\files\archive\tar\tarBinaryIsUsable;
use function oihana\files\archive\tar\tarCompressorExists;
use function oihana\files\archive\tar\tarEntries;
use function oihana\files\archive\tar\tarWithBinary;
use function oihana\files\archive\tar\untar;

/**
 * Two engines, one archive.
 *
 * `PharData` writes tar archives in pure PHP. On the same 96 MB / 7 554-file tree, producing
 * the same 17 MB archive, it took 317 seconds where GNU tar took 1.63 — and the gap widens
 * with size, since it writes the tar and reads it all back to compress it. It also refuses any
 * path component longer than the 100-byte ustar limit, which one file of a stock WordPress
 * plugin set is enough to hit: a backup of a real site could not be produced at all.
 *
 * So the system tar does the work when there is one worth trusting. What must not change is
 * what comes out: an archive is only interchangeable with those written before it if the
 * entries carry the same names. That is what these tests are about — the speed needs no test,
 * the fidelity does.
 */
class TarEngineTest extends TestCase
{
    /**
     * The `PATH` of the process before the test, put back after it.
     */
    private string|false $path ;

    private string $root ;

    protected function setUp() :void
    {
        $this->path = getenv( 'PATH' ) ;
        $this->root = sys_get_temp_dir() . '/oihana-tar-engine-' . bin2hex( random_bytes( 6 ) ) ;

        mkdir( $this->root , 0700 , true ) ;
    }

    protected function tearDown() :void
    {
        exec( sprintf( 'rm -rf %s' , escapeshellarg( $this->root ) ) ) ;

        putenv( 'OIHANA_TAR_BINARY' ) ;
        putenv( $this->path === false ? 'PATH' : 'PATH=' . $this->path ) ;

        tarBinary( refresh: true ) ;
    }

    // ------------------------------------------------------------------ the shared naming

    /**
     * A directory archived as its own root keeps its contents at the top level.
     */
    public function testEntriesOfADirectoryArchivedAsItsOwnRoot() :void
    {
        $tree = $this->tree() ;

        $names = $this->names( tarEntries( [ $tree ] , $tree ) ) ;

        $this->assertContains( 'plain.txt' , $names ) ;
        $this->assertContains( 'sub' , $names ) ;
        $this->assertContains( 'sub/nested.txt' , $names ) ;
        $this->assertNotContains( 'tree' , $names , 'Archived as its own root, the directory must not prefix its contents.' ) ;
    }

    /**
     * Archived from anywhere else it keeps its own name, so extracting recreates the directory
     * instead of spilling its contents where the operator stood.
     */
    public function testEntriesOfADirectoryArchivedFromItsParent() :void
    {
        $tree = $this->tree() ;

        $names = $this->names( tarEntries( [ $tree ] , dirname( $tree ) ) ) ;

        $this->assertContains( 'tree' , $names ) ;
        $this->assertContains( 'tree/plain.txt' , $names ) ;
        $this->assertContains( 'tree/sub/nested.txt' , $names ) ;
    }

    public function testEntriesOfALoneFile() :void
    {
        $tree = $this->tree() ;

        $this->assertSame( [ 'plain.txt' ] , $this->names( tarEntries( [ $tree . '/plain.txt' ] ) ) ) ;

        $this->assertSame
        (
            [ 'tree/plain.txt' ] ,
            $this->names( tarEntries( [ $tree . '/plain.txt' ] , dirname( $tree ) ) )
        ) ;
    }

    /**
     * An empty directory is an entry of its own, or it is lost.
     */
    public function testAnEmptyDirectoryIsAnEntry() :void
    {
        $tree = $this->tree() ;

        $this->assertContains( 'empty' , $this->names( tarEntries( [ $tree ] , $tree ) ) ) ;
    }

    /**
     * A path gone between the check of `tar()` and the listing is left out, not archived empty.
     */
    public function testAVanishedPathIsLeftOut() :void
    {
        $this->assertSame( [] , tarEntries( [ $this->root . '/vanished' ] ) ) ;
    }

    // ------------------------------------------------------------------ the selection

    public function testTheBinaryCanBeRefused() :void
    {
        putenv( 'OIHANA_TAR_BINARY=' ) ;

        $this->assertNull( tarBinary( refresh: true ) , 'An empty override must force the PharData engine.' ) ;
    }

    public function testAnUnusableBinaryIsNotUsed() :void
    {
        putenv( 'OIHANA_TAR_BINARY=' . $this->root . '/there-is-no-tar-here' ) ;

        $this->assertNull( tarBinary( refresh: true ) ) ;
    }

    /**
     * An executable file that cannot even be started — its interpreter is missing — is not asked
     * twice : the candidate is refused, the search goes on.
     */
    public function testACandidateThatCannotStartIsNotUsable() :void
    {
        $this->requireShell() ;

        $candidate = $this->script( 'broken-tar' , "#!/no/such/interpreter\n" ) ;

        $this->assertFalse( tarBinaryIsUsable( $candidate ) ) ;
    }

    /**
     * Without its compressor on the `PATH`, the system tar cannot write a compressed archive :
     * it steps aside before anything is written, and PharData does the work.
     */
    public function testTheBinaryStepsAsideWithoutItsCompressor() :void
    {
        $empty = $this->root . '/empty-bin' ;

        mkdir( $empty , 0700 ) ;

        putenv( 'PATH=' . $empty ) ;

        $entries = tarEntries( [ $this->tree() ] , $this->root . '/tree' ) ;

        $this->assertFalse( tarCompressorExists( CompressionType::GZIP ) ) ;
        $this->assertTrue ( tarCompressorExists( CompressionType::NONE ) , 'An uncompressed archive needs no compressor.' ) ;
        $this->assertFalse( tarBinaryHandles( $entries , CompressionType::GZIP ) ) ;
    }

    /**
     * A compression tar has no option for is left to PharData.
     */
    public function testTheBinaryStepsAsideForACompressionItCannotDo() :void
    {
        $entries = tarEntries( [ $this->tree() ] , $this->root . '/tree' ) ;

        $this->assertFalse( tarBinaryHandles( $entries , CompressionType::ZSTD ) ) ;
    }

    // ------------------------------------------------------------------ when the system tar fails

    /**
     * A tar that is present and fails is reporting something PharData would meet as well : the
     * half-written archive is removed, and the error carries what tar said.
     */
    public function testATarThatFailsLeavesNoHalfWrittenArchive() :void
    {
        $this->requireShell() ;

        $binary  = $this->failingTar( 'boom' ) ;
        $archive = $this->root . '/failed.tar' ;
        $entries = tarEntries( [ $this->tree() ] , $this->root . '/tree' ) ;

        try
        {
            tarWithBinary( $binary , $entries , $archive , CompressionType::NONE ) ;

            $this->fail( 'A tar exiting with 2 must raise.' ) ;
        }
        catch ( RuntimeException $exception )
        {
            $this->assertStringContainsString( 'exited with 2: boom' , $exception->getMessage() ) ;
        }

        $this->assertFileDoesNotExist( $archive , 'The half-written archive must be removed.' ) ;
    }

    public function testATarThatFailsSilentlySaysSo() :void
    {
        $this->requireShell() ;

        $entries = tarEntries( [ $this->tree() ] , $this->root . '/tree' ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'exited with 2: (no output)' ) ;

        tarWithBinary( $this->failingTar( null ) , $entries , $this->root . '/failed.tar' , CompressionType::NONE ) ;
    }

    public function testATarThatCannotRunIsReported() :void
    {
        $entries = tarEntries( [ $this->tree() ] , $this->root . '/tree' ) ;

        $this->expectException( RuntimeException::class ) ;

        tarWithBinary( $this->root . '/there-is-no-tar-here' , $entries , $this->root . '/never.tar' , CompressionType::NONE ) ;
    }

    // ------------------------------------------------------------------ PharData compresses

    /**
     * Where the system tar is refused or steps aside, PharData writes the tar and compresses it.
     * On a machine with a GNU tar no other test reaches this path : the binary compresses there.
     */
    public function testPharDataCompressesTheArchive() :void
    {
        putenv( 'OIHANA_TAR_BINARY=' ) ;
        tarBinary( refresh: true ) ;

        $tree      = $this->tree() ;
        $temporary = sys_get_temp_dir() . '/oihana/files/archive/tar/tar/temp_archive_*' ;
        $before    = glob( $temporary ) ?: [] ;

        foreach ( [ CompressionType::GZIP => '.tar.gz' , CompressionType::BZIP2 => '.tar.bz2' ] as $compression => $extension )
        {
            // One name per archive : PharData keeps the archives it opened by name for the whole
            // process, and the decompressed `.tar` of the first would collide with the second.
            $expected = $this->root . '/phar-' . $compression . $extension ;

            $archive = tar( $tree , $expected , $compression , $tree ) ;

            $this->assertSame( $expected , $archive ) ;

            $out = $this->root . '/out-' . $compression ;

            mkdir( $out , 0700 ) ;

            untar( $archive , $out ) ;

            $this->assertFileExists( $out . '/plain.txt'      , "The $compression archive must restore its files." ) ;
            $this->assertFileExists( $out . '/sub/nested.txt' , "The $compression archive must restore its files." ) ;
        }

        $this->assertSame( $before , glob( $temporary ) ?: [] , 'No temporary tar may be left behind.' ) ;
    }

    // ------------------------------------------------------------------ the two engines agree

    /**
     * The archive is the same whichever engine wrote it.
     *
     * Accents, CJK, quotes, spaces, an empty directory, a symlink and a deep path — the shapes
     * a real site is made of. Anything the two engines disagree on here is an archive that
     * restores differently depending on which machine wrote it.
     */
    public function testBothEnginesProduceTheSameEntries() :void
    {
        $binary = $this->requireBinary() ;

        $tree = $this->hostileTree() ;

        putenv( 'OIHANA_TAR_BINARY=' ) ;
        tarBinary( refresh: true ) ;

        $phar = tar( $tree , $this->root . '/phar.tar' , CompressionType::NONE , $tree ) ;

        putenv( 'OIHANA_TAR_BINARY=' . $binary ) ;
        tarBinary( refresh: true ) ;

        $system = tar( $tree , $this->root . '/system.tar' , CompressionType::NONE , $tree ) ;

        $this->assertSame
        (
            $this->archived( $phar ) ,
            $this->archived( $system ) ,
            'The two engines named the entries differently, so an archive written by one would '
            . 'not restore like an archive written by the other.'
        ) ;
    }

    /**
     * And what one writes, the other reads.
     *
     * `untar()` goes through PharData, so this is what keeps archives written by the binary
     * restorable by the code that has always restored them — and, by extension, keeps every
     * archive written before this change readable now.
     */
    public function testAnArchiveWrittenByTheBinaryIsReadByPharData() :void
    {
        $binary = $this->requireBinary() ;

        $tree = $this->hostileTree() ;

        putenv( 'OIHANA_TAR_BINARY=' . $binary ) ;
        tarBinary( refresh: true ) ;

        $archive = tar( $tree , $this->root . '/system.tar' , CompressionType::NONE , $tree ) ;

        mkdir( $this->root . '/out' , 0700 ) ;

        untar( $archive , $this->root . '/out' ) ;

        $this->assertFileExists( $this->root . '/out/été.txt' ) ;
        $this->assertFileExists( $this->root . '/out/sous dossier/nested.txt' ) ;
    }

    /**
     * The regression this whole change exists for.
     *
     * A path component past 100 bytes cannot be written in the ustar format PharData emits,
     * and it refuses rather than truncating. One file of a stock WordPress plugin set is over
     * it — `elementor-pro/assets/js/notes/vendors-node_modules_radix-ui_…js`, 103 bytes — so a
     * backup of an ordinary site could not be produced at all.
     */
    public function testALongNameIsRefusedByPharDataAndWrittenByTheBinary() :void
    {
        $binary = $this->requireBinary() ;

        $tree = $this->root . '/long' ;
        $name = str_repeat( 'n' , 120 ) . '.txt' ;

        mkdir( $tree , 0700 ) ;
        file_put_contents( $tree . '/' . $name , 'x' ) ;

        putenv( 'OIHANA_TAR_BINARY=' ) ;
        tarBinary( refresh: true ) ;

        try
        {
            tar( $tree , $this->root . '/phar.tar' , CompressionType::NONE , $tree ) ;

            $this->fail( 'PharData accepted a 120-byte name; the ustar format cannot hold one.' ) ;
        }
        catch ( RuntimeException )
        {
            // Expected, and the reason the binary engine exists.
        }

        putenv( 'OIHANA_TAR_BINARY=' . $binary ) ;
        tarBinary( refresh: true ) ;

        $archive = tar( $tree , $this->root . '/system.tar' , CompressionType::NONE , $tree ) ;

        $this->assertContains( $name , $this->archived( $archive ) ) ;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * A fake tar that reads its names, creates the archive it was asked for, writes `$message`
     * on its error output when there is one, then exits with 2 — what a real tar does on a full
     * disk.
     */
    private function failingTar( ?string $message ) :string
    {
        $say = $message === null ? '' : sprintf( "echo %s >&2\n" , escapeshellarg( $message ) ) ;

        return $this->script
        (
            'failing-tar-' . bin2hex( random_bytes( 3 ) ) ,
            "#!/bin/sh\n"
            . "cat > /dev/null\n"
            . "while [ \$# -gt 0 ]; do\n"
            . "  if [ \"\$1\" = '--file' ]; then : > \"\$2\"; fi\n"
            . "  shift\n"
            . "done\n"
            . $say
            . "exit 2\n"
        ) ;
    }

    /**
     * The fake binaries are shell scripts.
     */
    private function requireShell() :void
    {
        if ( strtoupper( substr( PHP_OS , 0 , 3 ) ) === 'WIN' || !is_executable( '/bin/sh' ) )
        {
            $this->markTestSkipped( 'No /bin/sh here — the fake binaries cannot run.' ) ;
        }
    }

    /**
     * Writes an executable script in the test directory.
     */
    private function script( string $name , string $content ) :string
    {
        $path = $this->root . '/' . $name ;

        file_put_contents( $path , $content ) ;
        chmod( $path , 0700 ) ;

        return $path ;
    }

    /**
     * A GNU tar, or a skipped test.
     *
     * macOS ships bsdtar, which normalises filenames to NFD and is therefore refused by
     * {@see tarBinary()}; these run on Linux, where the library's CI lives.
     */
    private function requireBinary() :string
    {
        putenv( 'OIHANA_TAR_BINARY' ) ;

        $binary = tarBinary( refresh: true ) ;

        if ( $binary === null )
        {
            $this->markTestSkipped( 'No GNU tar here — the binary engine cannot be exercised.' ) ;
        }

        return $binary ;
    }

    private function tree() :string
    {
        $tree = $this->root . '/tree' ;

        mkdir( $tree . '/sub' , 0700 , true ) ;
        mkdir( $tree . '/empty' , 0700 , true ) ;

        file_put_contents( $tree . '/plain.txt' , 'x' ) ;
        file_put_contents( $tree . '/sub/nested.txt' , 'x' ) ;

        return $tree ;
    }

    /**
     * The shapes a real site is made of, and the ones that break naive implementations.
     */
    private function hostileTree() :string
    {
        $tree = $this->root . '/hostile' ;

        mkdir( $tree . '/sous dossier' , 0700 , true ) ;
        mkdir( $tree . '/vide' , 0700 , true ) ;
        mkdir( $tree . '/a/b/c/d/e' , 0700 , true ) ;

        file_put_contents( $tree . '/été.txt' , 'x' ) ;
        file_put_contents( $tree . '/café ☕ 日本.txt' , 'x' ) ;
        file_put_contents( $tree . "/apostrophe'et\"guillemet.txt" , 'x' ) ;
        file_put_contents( $tree . '/sous dossier/nested.txt' , 'x' ) ;
        file_put_contents( $tree . '/a/b/c/d/e/deep.txt' , 'x' ) ;

        symlink( 'été.txt' , $tree . '/lien' ) ;

        return $tree ;
    }

    /**
     * @param array<int, array{base: string, name: string, directory: bool, path: string}> $entries
     *
     * @return array<int, string>
     */
    private function names( array $entries ) :array
    {
        return array_map( static fn( array $entry ) :string => $entry[ 'name' ] , $entries ) ;
    }

    /**
     * What an archive holds, normalised for the two differences that carry no meaning: tar
     * writes directories with a trailing slash and includes the root entry, PharData does
     * neither. Neither changes what an extraction produces.
     *
     * @return array<int, string>
     */
    private function archived( string $archive ) :array
    {
        exec( sprintf( 'tar -tf %s' , escapeshellarg( $archive ) ) , $lines ) ;

        $names = [] ;

        foreach ( $lines as $line )
        {
            $name = rtrim( $line , '/' ) ;

            if ( $name !== '' && $name !== '.' )
            {
                $names[ $name ] = true ;
            }
        }

        $names = array_keys( $names ) ;

        sort( $names ) ;

        return $names ;
    }
}
