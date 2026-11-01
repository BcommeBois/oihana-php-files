<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function oihana\files\archive\tar\tarCopyScript;

/**
 * The copy script, run for real by `bash` against two fake `tar`.
 *
 * The fakes stand first in the `PATH`. The one called with `-cf` plays the reading tar, the one
 * called with `-xf` the writing one; each exits with the code it is told to, and the reader
 * writes down the `LC_ALL` it was given. What is asserted is what the script exits with, which is
 * all {@see \oihana\files\archive\tar\tarCopyOutcome()} has to go on. No real tar is needed.
 */
#[CoversFunction('oihana\files\archive\tar\tarCopyScript')]
final class TarCopyScriptTest extends TestCase
{
    private string $bin ;

    private string $seen ;

    protected function setUp() :void
    {
        $this->bin  = sys_get_temp_dir() . '/oihana-fake-tar-' . bin2hex( random_bytes( 6 ) ) ;
        $this->seen = $this->bin . '/lc_all' ;

        mkdir( $this->bin , 0700 , true ) ;

        file_put_contents
        (
            $this->bin . '/tar' ,
            <<<'SH'
            #!/usr/bin/env bash
            case " $* " in
                *" -cf "*)
                    printf '%s' "${LC_ALL-unset}" > "$FAKE_SEEN"
                    printf 'data'
                    [ "$FAKE_READ" = 1 ] && echo "tar: content: file changed as we read it" >&2
                    exit "$FAKE_READ" ;;
                *" -xf "*)
                    cat > /dev/null
                    exit "$FAKE_WRITE" ;;
            esac
            exit 99
            SH
        ) ;

        chmod( $this->bin . '/tar' , 0700 ) ;
    }

    protected function tearDown() :void
    {
        exec( 'rm -rf ' . escapeshellarg( $this->bin ) ) ;
    }

    /**
     * @return array{status: int, error: string}
     */
    private function copy( int $read , int $write , string $lcAll = 'fr_FR.UTF-8' ) :array
    {
        $script = tarCopyScript( '/srv/app' , [ 'content' ] , '/tmp/copy' , [ 'content/cache' ] ) ;

        $process = proc_open
        (
            [ 'bash' , '-c' , $script ] ,
            [ 1 => [ 'pipe' , 'w' ] , 2 => [ 'pipe' , 'w' ] ] ,
            $pipes ,
            null ,
            [
                'PATH'       => $this->bin . ':' . getenv( 'PATH' ) ,
                'LC_ALL'     => $lcAll ,
                'FAKE_READ'  => (string) $read ,
                'FAKE_WRITE' => (string) $write ,
                'FAKE_SEEN'  => $this->seen ,
            ]
        ) ;

        $this->assertIsResource( $process ) ;

        stream_get_contents( $pipes[ 1 ] ) ;

        $error = (string) stream_get_contents( $pipes[ 2 ] ) ;

        fclose( $pipes[ 1 ] ) ;
        fclose( $pipes[ 2 ] ) ;

        return [ 'status' => proc_close( $process ) , 'error' => $error ] ;
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function codesProvider() :array
    {
        return
        [
            'both clean'                               => [ 0 , 0 , 0 ] ,
            'files moved while read'                   => [ 1 , 0 , 1 ] ,
            'a fatal error while reading'              => [ 2 , 0 , 2 ] ,
            'the copy could not be written'            => [ 0 , 2 , 3 ] ,
            'files moved, and the copy failed besides' => [ 1 , 2 , 3 ] ,
        ] ;
    }

    #[DataProvider('codesProvider')]
    public function testTheScriptExitsWithWhatTheTwoTarSaid( int $read , int $write , int $expected ) :void
    {
        $this->assertSame
        (
            $expected ,
            $this->copy( $read , $write )[ 'status' ] ,
            sprintf( 'The reading tar exited %d and the writing one %d.' , $read , $write )
        ) ;
    }

    public function testWhatTheReadingTarSaysReachesTheCaller() :void
    {
        $this->assertStringContainsString( 'file changed as we read it' , $this->copy( 1 , 0 )[ 'error' ] ) ;
    }

    /**
     * On a machine that speaks French, tar says « fichier modifié pendant sa lecture » without
     * `LC_ALL=C`, and the message is recognised nowhere.
     */
    public function testTheCopySpeaksEnglishWhateverTheMachineSpeaks() :void
    {
        $this->copy( 0 , 0 , 'fr_FR.UTF-8' ) ;

        $this->assertSame( 'C' , (string) file_get_contents( $this->seen ) , 'The reading tar did not run under LC_ALL=C.' ) ;
    }

    public function testTheScriptNamesWhatItCopiesAndWhatItLeavesOut() :void
    {
        $script = tarCopyScript( '/srv/app' , [ '.htaccess' , 'robots.txt' ] , '/tmp/copy/root' , [ 'content/cache' ] ) ;

        $this->assertStringContainsString
        (
            "tar -C '/srv/app' --exclude='content/cache' -cf - -- '.htaccess' 'robots.txt' | tar -C '/tmp/copy/root' -xf -" ,
            $script
        ) ;
    }

    public function testWithoutPatternsNothingIsLeftOut() :void
    {
        $this->assertStringContainsString( "tar -C '/srv/app' -cf - -- 'content' |" , tarCopyScript( '/srv/app' , [ 'content' ] , '/tmp/copy' ) ) ;
    }

    /**
     * A pattern is a value, never shell: a quote or a space in it stays inside its argument.
     */
    public function testEveryPathAndPatternIsEscapedForTheShell() :void
    {
        $script = tarCopyScript( "/srv/my app" , [ "it's" ] , '/tmp/a b' , [ "*'; rm -rf /; '" ] ) ;

        $this->assertStringContainsString( "-C '/srv/my app'" , $script ) ;
        $this->assertStringContainsString( "--exclude='*'\\''; rm -rf /; '\\'''" , $script ) ;
        $this->assertStringContainsString( "-- 'it'\\''s'" , $script ) ;
        $this->assertStringContainsString( "-C '/tmp/a b' -xf -" , $script ) ;
    }
}
