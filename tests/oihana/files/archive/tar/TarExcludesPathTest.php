<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function oihana\files\archive\tar\tarExcludesPath;

#[CoversFunction('oihana\files\archive\tar\tarExcludesPath')]
final class TarExcludesPathTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>, bool}>
     */
    public static function cases() :array
    {
        return
        [
            'the directory itself'              => [ 'content/backups'                , [ 'content/backups' ]          , true  ] ,
            'a file inside it'                  => [ 'content/backups/backup-1.zip'   , [ 'content/backups' ]          , true  ] ,
            'deep inside it'                    => [ 'content/backups/a/b/c.zip'      , [ 'content/backups' ]          , true  ] ,
            'a sibling with a longer name'      => [ 'content/backups-old/x.zip'      , [ 'content/backups' ]          , false ] ,
            'a segment, not a prefix'           => [ 'content/cached.txt'             , [ 'cache' ]                    , false ] ,
            'unanchored, as tar is'             => [ 'x/content/cache/page.html'      , [ 'content/cache' ]            , true  ] ,
            'a bare name, at any depth'         => [ 'content/app/cache/a.bin'        , [ 'cache' ]                    , true  ] ,
            'a star crosses a slash, as in tar' => [ 'content/uploads/2024/05/big.mp4', [ 'content/uploads/*.mp4' ]    , true  ] ,
            'a pattern on a name, anywhere'     => [ 'content/uploads/a.bak'          , [ '*.bak' ]                    , true  ] ,
            'a question mark'                   => [ 'content/log1'                   , [ 'log?' ]                     , true  ] ,
            'a bracket class'                   => [ 'content/log9'                   , [ 'log[0-5]' ]                 , false ] ,
            'a trailing slash in the pattern'   => [ 'content/cache/x'                , [ 'content/cache/' ]           , true  ] ,
            'a leading slash in the path'       => [ '/content/cache/x'               , [ 'content/cache' ]            , true  ] ,
            'nothing declared'                  => [ 'content/uploads/a.jpg'          , []                             , false ] ,
            'an empty pattern names nothing'    => [ 'content/uploads/a.jpg'          , [ '' , '/' ]                   , false ] ,
            'none of several patterns'          => [ 'content/uploads/a.jpg'          , [ 'content/cache' , 'backups' ], false ] ,
            'the second of several patterns'    => [ 'content/backups/a.zip'          , [ 'content/cache' , 'backups' ], true  ] ,
            'a name at the root'                => [ '.htaccess.bk'                   , [ '*.bk*' ]                    , true  ] ,
        ] ;
    }

    /**
     * @param list<string> $patterns
     */
    #[DataProvider('cases')]
    public function testReadsThePatternsAsGnuTarDoes( string $path , array $patterns , bool $expected ) :void
    {
        $this->assertSame( $expected , tarExcludesPath( $path , $patterns ) ) ;
    }

    /**
     * The cases above, held to GNU tar itself: what tar leaves out of an archive is what this
     * function says it leaves out.
     */
    public function testAgreesWithGnuTar() :void
    {
        $tar = $this->gnuTar() ;

        $root = sys_get_temp_dir() . '/oihana-tar-excludes-' . bin2hex( random_bytes( 6 ) ) ;

        $files =
        [
            'content/backups/a/b/c.zip' ,
            'content/backups-old/x.zip' ,
            'content/cached.txt' ,
            'content/app/cache/a.bin' ,
            'content/uploads/2024/05/big.mp4' ,
            'content/uploads/a.jpg' ,
            'content/uploads/a.bak' ,
        ] ;

        $patterns = [ 'content/backups' , 'cache' , 'content/uploads/*.mp4' , '*.bak' ] ;

        try
        {
            foreach ( $files as $file )
            {
                @mkdir( dirname( $root . '/' . $file ) , 0700 , true ) ;
                file_put_contents( $root . '/' . $file , 'x' ) ;
            }

            $command = sprintf( '%s -C %s -cf - ' , escapeshellarg( $tar ) , escapeshellarg( $root ) ) ;

            foreach ( $patterns as $pattern )
            {
                $command .= '--exclude=' . escapeshellarg( $pattern ) . ' ' ;
            }

            $command .= sprintf( 'content | %s -tf -' , escapeshellarg( $tar ) ) ;

            exec( $command , $listed ) ;

            foreach ( $files as $file )
            {
                $this->assertSame
                (
                    !in_array( $file , $listed , true ) ,
                    tarExcludesPath( $file , $patterns ) ,
                    sprintf( 'GNU tar and tarExcludesPath() disagree on %s.' , $file )
                ) ;
            }
        }
        finally
        {
            exec( sprintf( 'rm -rf %s' , escapeshellarg( $root ) ) ) ;
        }
    }

    private function gnuTar() :string
    {
        foreach ( [ 'gtar' , '/opt/homebrew/bin/gtar' , '/usr/local/bin/gtar' , 'tar' ] as $candidate )
        {
            $version = [] ;

            @exec( sprintf( '%s --version 2>/dev/null' , escapeshellarg( $candidate ) ) , $version ) ;

            if ( str_contains( $version[ 0 ] ?? '' , 'GNU tar' ) )
            {
                return $candidate ;
            }
        }

        $this->markTestSkipped( 'GNU tar is not installed.' ) ;
    }
}
