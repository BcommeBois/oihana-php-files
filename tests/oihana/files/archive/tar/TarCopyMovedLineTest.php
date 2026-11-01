<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use function oihana\files\archive\tar\tarCopyMovedLine;

#[CoversFunction('oihana\files\archive\tar\tarCopyMovedLine')]
final class TarCopyMovedLineTest extends TestCase
{
    public function testOneFile() :void
    {
        $this->assertSame
        (
            '1 file changed while content was copied, and was copied as found: content' ,
            tarCopyMovedLine( [ 'content' ] , 'content' )
        ) ;
    }

    public function testSeveralFiles() :void
    {
        $this->assertSame
        (
            '2 files changed while the site root was copied, and were copied as found: a, b' ,
            tarCopyMovedLine( [ 'a' , 'b' ] , 'the site root' )
        ) ;
    }

    public function testWithoutALimitEveryFileIsNamed() :void
    {
        $moved = array_map( static fn( int $i ) :string => 'f' . $i , range( 1 , 30 ) ) ;

        $this->assertSame
        (
            '30 files changed while content was copied, and were copied as found: ' . implode( ', ' , $moved ) ,
            tarCopyMovedLine( $moved , 'content' )
        ) ;
    }

    /**
     * A mail names a few: a cache folder that keeps changing must not make a message of a
     * thousand lines.
     */
    public function testALimitNamesThatManyAndCountsTheRest() :void
    {
        $moved = array_map( static fn( int $i ) :string => 'f' . $i , range( 1 , 12 ) ) ;

        $this->assertSame
        (
            '12 files changed while content was copied, and were copied as found: f1, f2, f3 … and 9 more' ,
            tarCopyMovedLine( $moved , 'content' , 3 ) ,
            'The count is of every file, and the rest is said, not dropped.'
        ) ;
    }

    public function testUnderTheLimitNothingIsCut() :void
    {
        $this->assertSame
        (
            '2 files changed while content was copied, and were copied as found: a, b' ,
            tarCopyMovedLine( [ 'a' , 'b' ] , 'content' , 3 )
        ) ;
    }

    public function testAtTheLimitNothingIsCut() :void
    {
        $this->assertSame
        (
            '3 files changed while content was copied, and were copied as found: a, b, c' ,
            tarCopyMovedLine( [ 'a' , 'b' , 'c' ] , 'content' , 3 )
        ) ;
    }

    public function testALimitBelowOneStillNamesOne() :void
    {
        $this->assertSame
        (
            '2 files changed while content was copied, and were copied as found: a … and 1 more' ,
            tarCopyMovedLine( [ 'a' , 'b' ] , 'content' , 0 )
        ) ;
    }

    public function testOneFileUnderALimitOfOne() :void
    {
        $this->assertSame
        (
            '1 file changed while content was copied, and was copied as found: a' ,
            tarCopyMovedLine( [ 'a' ] , 'content' , 1 )
        ) ;
    }

    /**
     * « 0 file changed … copied as found: » would be a sentence about nothing.
     */
    public function testNoFileSaysNothing() :void
    {
        $this->assertSame( '' , tarCopyMovedLine( [] , 'content' ) ) ;
        $this->assertSame( '' , tarCopyMovedLine( [] , 'content' , 3 ) ) ;
    }
}
