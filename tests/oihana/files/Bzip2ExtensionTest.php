<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

/**
 * What `bzip2File()` and `bunzip2File()` do on a host that cannot call them.
 *
 * `ext-bz2` is a suggestion of this package, so a Debian with `php-cli` and no
 * `php-bz2` is a host this library has to behave on. {@see Bzip2FileTest} covers that
 * from the inside, by asserting the refusal when the functions are missing — but on a
 * machine that has the extension, which is every machine this suite usually runs on,
 * that branch is never taken and the guard is never seen.
 *
 * So it is staged: a child started with `disable_functions=bzopen,…`, which removes the
 * functions from the table so `function_exists()` answers false, exactly as a build
 * without the extension does. A build that ignores the directive is detected, and the
 * test then asserts the other outcome rather than skipping — `failOnSkipped` is on here,
 * so a skip would fail the suite it is meant to keep green.
 */
class Bzip2ExtensionTest extends TestCase
{
    private const string DISABLED = 'bzopen,bzwrite,bzread,bzclose,bzerrno,bzerrstr,bzcompress,bzdecompress' ;

    private string $root ;

    protected function setUp(): void
    {
        if ( DIRECTORY_SEPARATOR === '\\' )
        {
            $this->markTestSkipped( 'The child process is launched with a POSIX command line.' ) ;
        }

        $this->root = sys_get_temp_dir() . '/oihana-bz2-extension-' . bin2hex( random_bytes( 6 ) ) ;

        mkdir( $this->root , 0777 , true ) ;

        file_put_contents( $this->root . '/source.txt' , str_repeat( 'du contenu à compresser ' , 50 ) ) ;
        file_put_contents( $this->root . '/probe.php' , <<<'CHILD'
        <?php

        use function oihana\files\bunzip2File;
        use function oihana\files\bzip2File;

        require $argv[ 1 ] ;

        echo 'BZ2=' , function_exists('bzopen') ? 'callable' : 'absent' , "\n" ;

        foreach ( [ 'bzip2File' , 'bunzip2File' ] as $function )
        {
            try
            {
                $function === 'bzip2File'
                    ? bzip2File( $argv[ 2 ] , $argv[ 3 ] )
                    : bunzip2File( $argv[ 2 ] , $argv[ 4 ] ) ;

                echo $function , '=ok' , "\n" ;
            }
            catch ( Throwable $e )
            {
                printf( "%s=%s: %s\n" , $function , ( new ReflectionClass( $e ) )->getShortName() , $e->getMessage() ) ;
            }
        }

        echo 'WROTE=' , implode( ',' , array_map( 'basename' , glob( dirname( $argv[ 2 ] ) . '/out.*' ) ?: [] ) ) , "\n" ;
        CHILD ) ;
    }

    protected function tearDown(): void
    {
        foreach ( glob( $this->root . '/*' ) ?: [] as $file )
        {
            unlink( $file ) ;
        }

        if ( is_dir( $this->root ) )
        {
            rmdir( $this->root ) ;
        }
    }

    private function runChild( bool $withExtension ) :string
    {
        $command = sprintf
        (
            '%s%s %s %s %s %s %s 2>&1' ,
            escapeshellarg( PHP_BINARY ) ,
            $withExtension ? '' : ' -d ' . escapeshellarg( 'disable_functions=' . self::DISABLED ) ,
            escapeshellarg( $this->root . '/probe.php' ) ,
            escapeshellarg( dirname( __DIR__ , 3 ) . '/vendor/autoload.php' ) ,
            escapeshellarg( $this->root . '/source.txt' ) ,
            escapeshellarg( $this->root . '/out.bz2' ) ,
            escapeshellarg( $this->root . '/out.txt' )
        ) ;

        $lines = [] ;
        exec( $command , $lines ) ;

        return implode( "\n" , $lines ) ;
    }

    /**
     * The failure this replaces was not an exception at all: without the guard, PHP dies
     * on `Call to undefined function bzopen()` — a fatal error, so nothing to catch and
     * nothing for a caller to report.
     */
    public function testBothFunctionsRefuseByNameWhereTheyCannotRun(): void
    {
        $report = $this->runChild( false ) ;

        if ( !str_contains( $report , 'BZ2=absent' ) )
        {
            // The build ignores disable_functions. There is still something true to
            // assert, and it is the other half of the contract.
            $this->assertStringContainsString( 'bzip2File=ok' , $report , 'With the functions callable, compression must simply work' ) ;
            return ;
        }

        $this->assertStringContainsString( 'bzip2File=FileException: The "bz2" PHP extension is required to bzip2 files.' , $report ) ;
        $this->assertStringContainsString( 'bunzip2File=FileException: The "bz2" PHP extension is required to bunzip2 files.' , $report ) ;

        $this->assertStringNotContainsString( 'Call to undefined function' , $report , 'A fatal error is what the guard exists to prevent' ) ;
    }

    /**
     * And it refuses **before** touching anything: the extension is checked ahead of the
     * destination, so a host without it writes no half file and reports the real reason
     * rather than a complaint about the target.
     */
    public function testNothingIsWrittenWhenTheExtensionIsMissing(): void
    {
        $report = $this->runChild( false ) ;

        if ( !str_contains( $report , 'BZ2=absent' ) )
        {
            $this->assertStringContainsString( 'WROTE=out.bz2' , $report , 'With the functions callable, the output must be there' ) ;
            return ;
        }

        $this->assertStringContainsString( 'WROTE=' . "\n" , $report . "\n" , 'No file may be left behind' ) ;
    }

    /**
     * The control: the same child, with nothing disabled, compresses and decompresses.
     * Without it, the two tests above would pass on a host where the functions never
     * worked at all.
     */
    public function testTheSameChildSucceedsWithTheFunctionsAvailable(): void
    {
        $report = $this->runChild( true ) ;

        // What the **child** reports, not what this process can do: when the suite itself
        // is run with the functions disabled, the child started without the flag still has
        // them, and that is the control working as intended.

        if ( str_contains( $report , 'BZ2=absent' ) )
        {
            $this->assertStringContainsString( 'extension is required' , $report , 'This host has no bz2 at all, so the refusal is what there is to assert' ) ;
            return ;
        }

        $this->assertStringContainsString( 'BZ2=callable' , $report ) ;
        $this->assertStringContainsString( 'bzip2File=ok' , $report ) ;
        $this->assertStringContainsString( 'bunzip2File=ok' , $report ) ;
    }
}
