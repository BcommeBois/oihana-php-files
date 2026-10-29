<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\TestCase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards `src/` against `finfo_close()`.
 *
 * Since PHP 8.1 `finfo_open()` returns a `finfo` object freed on its own, so `finfo_close()` does
 * nothing; PHP 8.5 deprecates it and prints a notice on every call. Comments are stripped before
 * the search, so the function may still be named in a docblock.
 */
class SourceHasNoFinfoCloseTest extends TestCase
{
    public function testTheSourceNeverCallsFinfoClose(): void
    {
        $offenders = [] ;

        $files = new RecursiveIteratorIterator
        (
            new RecursiveDirectoryIterator( dirname( __DIR__ , 3 ) . '/src' , RecursiveDirectoryIterator::SKIP_DOTS )
        ) ;

        /** @var SplFileInfo $file */
        foreach ( $files as $file )
        {
            if ( $file->getExtension() !== 'php' )
            {
                continue ;
            }

            if ( str_contains( $this->withoutComments( file_get_contents( $file->getPathname() ) ) , 'finfo_close(' ) )
            {
                $offenders[] = $file->getPathname() ;
            }
        }

        $this->assertSame( [] , $offenders , 'finfo_close() is deprecated since PHP 8.5 : remove it from these files.' ) ;
    }

    private function withoutComments( string $source ): string
    {
        $code = '' ;

        foreach ( token_get_all( $source ) as $token )
        {
            if ( is_array( $token ) )
            {
                if ( $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT )
                {
                    continue ;
                }
                $code .= $token[1] ;
            }
            else
            {
                $code .= $token ;
            }
        }

        return $code ;
    }
}
