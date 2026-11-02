<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\FileException;

use function oihana\files\checksumLine;
use function oihana\files\readChecksumLine;

#[CoversFunction('oihana\files\readChecksumLine')]
final class ReadChecksumLineTest extends TestCase
{
    private const string NAME = 'archive.tar.gz.enc' ;

    public function testTheLineOfTheFileIsRead() :void
    {
        $hash = str_repeat( 'b' , 64 ) ;

        $this->assertSame( $hash , readChecksumLine( $hash . '  ' . self::NAME . "\n" , self::NAME ) ) ;
    }

    public function testTheBinaryModeOfSha256sumIsReadToo() :void
    {
        $hash = str_repeat( 'c' , 64 ) ;

        $this->assertSame( $hash , readChecksumLine( $hash . ' *' . self::NAME , self::NAME ) ) ;
    }

    public function testAWindowsLineEndIsRead() :void
    {
        $hash = str_repeat( 'd' , 64 ) ;

        $this->assertSame( $hash , readChecksumLine( $hash . '  ' . self::NAME . "\r\n" , self::NAME ) ) ;
    }

    public function testTheHashIsReadInLowercase() :void
    {
        $this->assertSame( str_repeat( 'e' , 64 ) , readChecksumLine( str_repeat( 'E' , 64 ) . '  ' . self::NAME , self::NAME ) ) ;
    }

    /**
     * `sha256sum a b c > SHA256SUMS` lists several files: the line of this one is found.
     */
    public function testTheLineOfTheFileIsFoundAmongOthers() :void
    {
        $content = str_repeat( '1' , 64 ) . "  first.tar.gz\n"
                 . str_repeat( '2' , 64 ) . '  ' . self::NAME . "\n"
                 . str_repeat( '3' , 64 ) . "  last.tar.gz\n" ;

        $this->assertSame( str_repeat( '2' , 64 ) , readChecksumLine( $content , self::NAME ) ) ;
    }

    public function testTheFirstLineNamingTheFileIsTaken() :void
    {
        $content = str_repeat( '1' , 64 ) . '  ' . self::NAME . "\n"
                 . str_repeat( '2' , 64 ) . '  ' . self::NAME . "\n" ;

        $this->assertSame( str_repeat( '1' , 64 ) , readChecksumLine( $content , self::NAME ) ) ;
    }

    public function testWhatChecksumLineWritesIsReadBack() :void
    {
        $hash = hash( 'sha256' , 'the archive' ) ;

        $this->assertSame( $hash , readChecksumLine( checksumLine( $hash , self::NAME ) , self::NAME ) ) ;
    }

    public function testAnotherAlgorithmReadsItsOwnLength() :void
    {
        $md5 = hash( 'md5' , 'the archive' ) ;

        $this->assertSame( $md5 , readChecksumLine( $md5 . '  ' . self::NAME , self::NAME , 'md5' ) ) ;

        $sha512 = hash( 'sha512' , 'the archive' ) ;

        $this->assertSame( $sha512 , readChecksumLine( $sha512 . '  ' . self::NAME , self::NAME , 'sha512' ) ) ;
    }

    public function testAHashOfAnotherAlgorithmIsRefused() :void
    {
        $this->assertNull
        (
            readChecksumLine( str_repeat( 'a' , 64 ) . '  ' . self::NAME , self::NAME , 'md5' ) ,
            'A SHA-256 was taken for an md5.'
        ) ;

        $this->assertNull
        (
            readChecksumLine( hash( 'md5' , 'x' ) . '  ' . self::NAME , self::NAME ) ,
            'An md5 was taken for a SHA-256.'
        ) ;
    }

    public function testAnUnsupportedAlgorithmIsRefused() :void
    {
        try
        {
            readChecksumLine( str_repeat( 'a' , 64 ) . '  ' . self::NAME , self::NAME , 'nope' ) ;
        }
        catch ( FileException $exception )
        {
            $this->assertSame( 'Unsupported hash algorithm "nope".' , $exception->getMessage() ) ;
            return ;
        }

        $this->fail( 'An unsupported algorithm was accepted.' ) ;
    }

    #[DataProvider('unreadableProvider')]
    public function testWhatDoesNotNameTheFileIsNotAChecksum( string $content , string $why ) :void
    {
        $this->assertNull( readChecksumLine( $content , self::NAME ) , $why ) ;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unreadableProvider() :array
    {
        return
        [
            'empty'         => [ '' , 'An empty file vouched for the file.' ] ,
            'another name'  => [ str_repeat( 'a' , 64 ) . '  other.tar.gz.enc' , 'The checksum of another file vouched for this one.' ] ,
            'a longer name' => [ str_repeat( 'a' , 64 ) . '  ' . self::NAME . '.old' , 'A name that only starts with this one vouched for it.' ] ,
            'too short'     => [ str_repeat( 'a' , 63 ) . '  ' . self::NAME , 'A hash of 63 characters was taken for a SHA-256.' ] ,
            'too long'      => [ str_repeat( 'a' , 65 ) . '  ' . self::NAME , 'A hash of 65 characters was taken for a SHA-256.' ] ,
            'not hex'       => [ str_repeat( 'g' , 64 ) . '  ' . self::NAME , 'A hash that is not hexadecimal was read.' ] ,
            'one space'     => [ str_repeat( 'a' , 64 ) . ' ' . self::NAME , 'A line sha256sum does not write was read.' ] ,
            'with a folder' => [ str_repeat( 'a' , 64 ) . '  backups/' . self::NAME , 'A path was taken for the file\'s name.' ] ,
            'indented'      => [ ' ' . str_repeat( 'a' , 64 ) . '  ' . self::NAME , 'A line that does not start with the hash was read.' ] ,
        ] ;
    }
}
