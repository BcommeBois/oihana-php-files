<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function oihana\files\archive\tar\tarCopyOutcome;

/**
 * What a copy amounts to, from the exit code of the script and the words of GNU tar.
 */
#[CoversFunction('oihana\files\archive\tar\tarCopyOutcome')]
final class TarCopyOutcomeTest extends TestCase
{
    public function testACleanCopyStands() :void
    {
        $this->assertSame( [ 'ok' => true , 'moved' => [] , 'reason' => '' ] , tarCopyOutcome( 0 , '' ) ) ;
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function movedProvider() :array
    {
        return
        [
            'a file written to during the copy' =>
            [
                'tar: content: file changed as we read it' ,
                [ 'content' ] ,
            ] ,
            'a file removed before it was read' =>
            [
                'tar: content/cache/page-12.html: File removed before we read it' ,
                [ 'content/cache/page-12.html' ] ,
            ] ,
            'a file that shrank' =>
            [
                'tar: content/debug.log: File shrank by 2048 bytes; padding with zeros' ,
                [ 'content/debug.log' ] ,
            ] ,
            'all three at once, among blank lines' =>
            [
                "\ntar: content: file changed as we read it\n\ntar: content/a b.txt: File removed before we read it\ntar: content/x.log: File shrank by 1 bytes; padding with zeros\n" ,
                [ 'content' , 'content/a b.txt' , 'content/x.log' ] ,
            ] ,
        ] ;
    }

    /**
     * @param list<string> $moved
     */
    #[DataProvider('movedProvider')]
    public function testFilesThatMovedWhileReadLetTheCopyStand( string $error , array $moved ) :void
    {
        $this->assertSame
        (
            [ 'ok' => true , 'moved' => $moved , 'reason' => '' ] ,
            tarCopyOutcome( 1 , $error ) ,
            'A file caught in motion is no reason to lose the copy: the files are named instead.'
        ) ;
    }

    public function testOneOtherLineFailsTheCopy() :void
    {
        $outcome = tarCopyOutcome( 1 , "tar: content: file changed as we read it\ntar: content/private.php: Cannot open: Permission denied" ) ;

        $this->assertFalse( $outcome[ 'ok' ] , 'A message that is not about a file in motion was waved through with it.' ) ;
        $this->assertSame( [] , $outcome[ 'moved' ] ) ;
        $this->assertSame
        (
            'tar: content: file changed as we read it / tar: content/private.php: Cannot open: Permission denied' ,
            $outcome[ 'reason' ] ,
            'The refusal does not say everything tar said.'
        ) ;
    }

    /**
     * A message is recognised whole: one that only begins like a moved file is something else.
     */
    public function testAMessageThatOnlyLooksLikeAMovedFileFailsTheCopy() :void
    {
        $this->assertFalse( tarCopyOutcome( 1 , 'tar: content: file changed as we read it, and then some' )[ 'ok' ] ) ;
        $this->assertFalse( tarCopyOutcome( 1 , 'gtar: content: file changed as we read it' )[ 'ok' ] ) ;
    }

    public function testACodeOneWithNothingSaidFailsTheCopy() :void
    {
        $outcome = tarCopyOutcome( 1 , "  \n" ) ;

        $this->assertFalse( $outcome[ 'ok' ] ) ;
        $this->assertSame( 'tar exited with 1 and said nothing' , $outcome[ 'reason' ] ) ;
    }

    public function testAFatalErrorFailsTheCopy() :void
    {
        $outcome = tarCopyOutcome( 2 , "tar: content: Cannot stat: No such file or directory\ntar: Exiting with failure status due to previous errors" ) ;

        $this->assertFalse( $outcome[ 'ok' ] ) ;
        $this->assertSame( [] , $outcome[ 'moved' ] ) ;
        $this->assertSame
        (
            'tar: content: Cannot stat: No such file or directory / tar: Exiting with failure status due to previous errors' ,
            $outcome[ 'reason' ]
        ) ;
    }

    /**
     * Code 2 is a failure even when tar only wrote the line of a moved file.
     */
    public function testAFatalErrorIsNeverReadAsAMovedFile() :void
    {
        $this->assertFalse( tarCopyOutcome( 2 , 'tar: content: file changed as we read it' )[ 'ok' ] ) ;
    }

    public function testAFatalErrorWithNothingSaidStillSaysTheCode() :void
    {
        $this->assertSame( 'tar exited with 2 and said nothing' , tarCopyOutcome( 2 , '' )[ 'reason' ] ) ;
    }

    public function testACopyThatCouldNotBeWrittenFailsEvenIfTheReadingWasClean() :void
    {
        $outcome = tarCopyOutcome( 3 , 'tar: content/uploads/big.mp4: Cannot write: No space left on device' ) ;

        $this->assertFalse( $outcome[ 'ok' ] , 'The writing tar failed and the copy was accepted: a full disk passing for a copy.' ) ;
        $this->assertSame( [] , $outcome[ 'moved' ] ) ;
        $this->assertSame
        (
            'the copy could not be written to its destination: tar: content/uploads/big.mp4: Cannot write: No space left on device' ,
            $outcome[ 'reason' ]
        ) ;
    }

    /**
     * Code 3 wins over the words of the reading tar: a moved file does not save a copy that was
     * never written.
     */
    public function testACopyThatCouldNotBeWrittenFailsEvenWithOnlyMovedFilesSaid() :void
    {
        $this->assertFalse( tarCopyOutcome( 3 , 'tar: content: file changed as we read it' )[ 'ok' ] ) ;
    }

    public function testACopyThatCouldNotBeWrittenWithNothingSaid() :void
    {
        $this->assertSame( 'the copy could not be written to its destination' , tarCopyOutcome( 3 , '' )[ 'reason' ] ) ;
    }
}
