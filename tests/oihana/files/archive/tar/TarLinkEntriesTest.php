<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\deleteDirectory;
use function oihana\files\archive\tar\tarHeaderSize;
use function oihana\files\archive\tar\tarLinkEntries;
use function oihana\files\archive\tar\tarPaxRecords;

/**
 * Reading the headers, because PharData will not tell.
 *
 * A link entry comes back from a PharData iterator as a regular file of size 0, with isLink()
 * answering false, so the only way to know an archive holds one is to read the headers. They are
 * fixed and documented: the type flag at offset 156, the link name at 157, and the name carried by
 * a GNU long-name block or a pax record when it does not fit.
 */
class TarLinkEntriesTest extends TestCase
{
    private string $root ;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/oihana-tar-links-' . bin2hex( random_bytes( 6 ) ) ;

        mkdir( $this->root . '/tree' , 0700 , true ) ;
    }

    protected function tearDown(): void
    {
        if ( is_dir( $this->root ) )
        {
            deleteDirectory( $this->root , assertable: false ) ;
        }
    }

    public function testFindsAHardLink(): void
    {
        // Of two names for one inode, tar stores the first it meets as the file and the second as
        // the link. The names decide which is which, since the fixture archives them sorted.

        file_put_contents( $this->root . '/tree/a-real.txt' , 'real' ) ;
        link( $this->root . '/tree/a-real.txt' , $this->root . '/tree/z-hard.txt' ) ;

        $links = tarLinkEntries( $this->archive() ) ;

        $this->assertCount( 1 , $links ) ;
        $this->assertSame( 'z-hard.txt' , $links[ 0 ][ 'name' ] ) ;
        $this->assertTrue( $links[ 0 ][ 'hard' ] ) ;
        $this->assertSame( 'a-real.txt' , $links[ 0 ][ 'target' ] ) ;
    }

    public function testFindsASymbolicLinkAndItsTarget(): void
    {
        file_put_contents( $this->root . '/tree/real.txt' , 'real' ) ;
        symlink( 'real.txt' , $this->root . '/tree/link.txt' ) ;

        $links = tarLinkEntries( $this->archive() ) ;

        $this->assertCount( 1 , $links ) ;
        $this->assertSame( 'link.txt' , $links[ 0 ][ 'name' ] ) ;
        $this->assertSame( 'real.txt' , $links[ 0 ][ 'target' ] ) ;
        $this->assertFalse( $links[ 0 ][ 'hard' ] ) ;
    }

    public function testReadsALongTargetFromAGnuLongLinkHeader(): void
    {
        $target = '/var/data/' . str_repeat( 'long-' , 24 ) . 'uploads' ;

        $this->assertGreaterThan( 100 , strlen( $target ) , 'The target must not fit in the header field.' ) ;

        symlink( $target , $this->root . '/tree/pointer' ) ;

        $links = tarLinkEntries( $this->archive( 'gnu' ) ) ;

        $this->assertCount( 1 , $links ) ;
        $this->assertSame( 'pointer' , $links[ 0 ][ 'name' ] ) ;
        $this->assertSame( $target , $links[ 0 ][ 'target' ] ) ;
    }

    public function testReadsALongTargetFromAPaxHeader(): void
    {
        $target = '/var/data/' . str_repeat( 'long-' , 24 ) . 'uploads' ;

        symlink( $target , $this->root . '/tree/pointer' ) ;

        $links = tarLinkEntries( $this->archive( 'pax' ) ) ;

        $this->assertCount( 1 , $links ) ;
        $this->assertSame( 'pointer' , $links[ 0 ][ 'name' ] ) ;
        $this->assertSame( $target , $links[ 0 ][ 'target' ] ) ;
    }

    public function testReturnsNothingForAnArchiveWithoutLinks(): void
    {
        mkdir( $this->root . '/tree/sub' , 0700 ) ;
        file_put_contents( $this->root . '/tree/one.txt' , 'x' ) ;
        file_put_contents( $this->root . '/tree/sub/two.txt' , 'y' ) ;

        $this->assertSame( [] , tarLinkEntries( $this->archive() ) ) ;
    }

    public function testSkipsTheDataOfARegularFileToReachALinkBehindIt(): void
    {
        // Two thousand bytes is four data blocks: a reader that forgets to skip them reads the
        // file's own content as the next header and finds nothing, or throws.

        file_put_contents( $this->root . '/tree/a-big.txt' , str_repeat( 'x' , 2000 ) ) ;
        symlink( 'a-big.txt' , $this->root . '/tree/z-link' ) ;

        $links = tarLinkEntries( $this->archive() ) ;

        $this->assertCount( 1 , $links ) ;
        $this->assertSame( 'z-link' , $links[ 0 ][ 'name' ] ) ;
    }

    public function testThrowsOnAFileThatIsNotATar(): void
    {
        $path = $this->root . '/not-a-tar' ;

        file_put_contents( $path , str_repeat( 'n' , 2048 ) ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/holds a block that is not a tar header/' ) ;

        tarLinkEntries( $path ) ;
    }

    public function testThrowsWhenTheFileCannotBeOpened(): void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/could not open/' ) ;

        tarLinkEntries( $this->root . '/no-such-archive.tar' ) ;
    }

    public function testTheSizeFieldIsReadInBase256WhenItsHighBitIsSet(): void
    {
        // GNU writes a base-256 integer where twelve octal digits cannot hold the size. The field
        // is built here rather than archived, since the shortest file that needs it is 8 GB.

        $header = str_repeat( "\0" , 512 ) ;
        $field  = "\x80" . "\x00\x00\x00\x00\x00\x00\x00\x02\x00\x00\x00" ;

        $this->assertSame( 12 , strlen( $field ) ) ;

        $header = substr_replace( $header , $field , 124 , 12 ) ;

        $this->assertSame( 2 << 24 , tarHeaderSize( $header ) ) ;
    }

    public function testThePaxRecordsAreWalkedByTheLengthTheyDeclare(): void
    {
        // A value may hold a space, and a record its own length, so splitting on either loses.

        // 27 = 2 digits + 1 space + 23 for "path=a file with spaces" + 1 newline.
        $records = tarPaxRecords( "27 path=a file with spaces\n23 linkpath=/var/da ta\n" ) ;

        $this->assertSame( 'a file with spaces' , $records[ 'path' ] ) ;
        $this->assertSame( '/var/da ta' , $records[ 'linkpath' ] ) ;
    }

    /**
     * Archives the tree with the system tar, in the format asked for, and returns the archive.
     */
    private function archive( ?string $format = null ): string
    {
        $tree = $this->root . '/tree' ;
        $path = $this->root . '/out.tar' ;

        // The entries are named one by one rather than archiving ".", which would prefix every
        // name in the archive with "./" and say nothing about the reader.

        $names = [] ;

        foreach ( scandir( $tree ) ?: [] as $name )
        {
            if ( $name !== '.' && $name !== '..' )
            {
                $names[] = escapeshellarg( $name ) ;
            }
        }

        // "gnu" is GNU tar's name for the format and "gnutar" is bsdtar's, so both are tried.

        foreach ( $format === null ? [ null ] : [ $format , $format === 'gnu' ? 'gnutar' : $format ] as $candidate )
        {
            @unlink( $path ) ;

            exec
            (
                sprintf
                (
                    'tar -cf %s %s-C %s %s 2>&1' ,
                    escapeshellarg( $path ) ,
                    $candidate === null ? '' : sprintf( '--format=%s ' , $candidate ) ,
                    escapeshellarg( $tree ) ,
                    implode( ' ' , $names )
                ) ,
                $output ,
                $status
            ) ;

            if ( $status === 0 && is_file( $path ) )
            {
                return $path ;
            }
        }

        $this->markTestSkipped( 'The system tar could not write the fixture: ' . implode( ' ' , $output ?? [] ) ) ;
    }
}
