<?php

namespace tests\oihana\files;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function oihana\files\assertRemovableDirectory;

#[CoversFunction('oihana\files\assertRemovableDirectory')]
final class AssertRemovableDirectoryTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function unsafeProvider() :array
    {
        return
        [
            'empty'            => [ ''                        , 'empty'          ] ,
            'blank'            => [ '   '                     , 'empty'          ] ,
            'root'             => [ '/'                       , '0 segment(s)'   ] ,
            'single segment'   => [ '/var'                    , '1 segment(s)'   ] ,
            'two segments'     => [ '/var/www'                , '2 segment(s)'   ] ,
            'doubled slashes'  => [ '//var//www//'            , '2 segment(s)'   ] ,
            'relative'         => [ 'var/www/example/htdocs'  , 'relative directory path' ] ,
            'home shorthand'   => [ '~/sites/example'         , 'relative directory path' ] ,
            'parent traversal' => [ '/var/www/../../etc'      , 'relative segments' ] ,
            'current segment'  => [ '/var/www/./htdocs'       , 'relative segments' ] ,
        ] ;
    }

    #[DataProvider('unsafeProvider')]
    public function testRefusesAnUnsafePath( string $path , string $reason ) :void
    {
        try
        {
            assertRemovableDirectory( $path ) ;
        }
        catch ( DirectoryException $exception )
        {
            $this->assertStringContainsString( $reason , $exception->getMessage() ) ;

            return ;
        }

        $this->fail( sprintf( '"%s" was declared safe to delete.' , $path ) ) ;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function safeProvider() :array
    {
        return
        [
            'a deep path'       => [ '/var/www/example.com/www/htdocs' ] ,
            'the minimum depth' => [ '/srv/example/htdocs'             ] ,
            'a trailing slash'  => [ '/var/www/example.com/htdocs/'    ] ,
            'blanks around'     => [ '  /srv/example/htdocs  '         ] ,
            'a dotted name'     => [ '/srv/example/.cache'             ] ,
        ] ;
    }

    #[DataProvider('safeProvider')]
    public function testAcceptsADeepAbsolutePath( string $path ) :void
    {
        $this->expectNotToPerformAssertions() ;

        assertRemovableDirectory( $path ) ;
    }

    public function testTheMinimumDepthCanBeChanged() :void
    {
        assertRemovableDirectory( '/var/www' , minDepth: 2 ) ;

        $this->expectException( DirectoryException::class ) ;

        assertRemovableDirectory( '/srv/example/htdocs' , minDepth: 4 ) ;
    }

    public function testTheMessageNamesThePathAndTheDepths() :void
    {
        try
        {
            assertRemovableDirectory( '/var/www' ) ;
        }
        catch ( DirectoryException $exception )
        {
            $this->assertSame
            (
                'Refusing to delete "/var/www": the path is too close to the filesystem root (2 segment(s), 3 required).' ,
                $exception->getMessage() ,
                'A refusal that does not name the path leaves nobody able to tell which value was wrong.'
            ) ;

            return ;
        }

        $this->fail( 'A path two segments from the root was declared safe to delete.' ) ;
    }
}
