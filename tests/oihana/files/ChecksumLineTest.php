<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use function oihana\files\checksumLine;

#[CoversFunction('oihana\files\checksumLine')]
final class ChecksumLineTest extends TestCase
{
    public function testTheLineIsTheOneSha256sumWrites() :void
    {
        $this->assertSame
        (
            str_repeat( 'a' , 64 ) . "  archive.tar.gz.enc\n" ,
            checksumLine( str_repeat( 'A' , 64 ) , 'archive.tar.gz.enc' ) ,
            'Not the format sha256sum -c reads: the hash in lowercase, two spaces, the name, a line feed.'
        ) ;
    }

    public function testAnyLengthOfHashIsWritten() :void
    {
        $this->assertSame( hash( 'md5' , 'x' ) . "  report.pdf\n" , checksumLine( hash( 'md5' , 'x' ) , 'report.pdf' ) ) ;
    }

    /**
     * And `sha256sum -c` reads it, beside the file, without PHP.
     */
    public function testSha256sumChecksIt() :void
    {
        // sha256sum on Linux; shasum -a 256 on macOS, which reads the same line.

        $tool = trim( (string) shell_exec( 'command -v sha256sum 2>/dev/null' ) ) ;
        $tool = $tool !== '' ? escapeshellarg( $tool ) : ( trim( (string) shell_exec( 'command -v shasum 2>/dev/null' ) ) !== '' ? 'shasum -a 256' : '' ) ;

        if ( $tool === '' )
        {
            $this->markTestSkipped( 'Neither sha256sum nor shasum is on this machine.' ) ;
        }

        $directory = sys_get_temp_dir() . '/checksum-line-' . bin2hex( random_bytes( 4 ) ) ;
        mkdir( $directory ) ;

        try
        {
            file_put_contents( $directory . '/archive.tar.gz' , 'the archive' ) ;
            file_put_contents( $directory . '/archive.tar.gz.sha256' , checksumLine( hash( 'sha256' , 'the archive' ) , 'archive.tar.gz' ) ) ;

            exec( sprintf( 'cd %s && %s -c archive.tar.gz.sha256 2>&1' , escapeshellarg( $directory ) , $tool ) , $said , $status ) ;

            $this->assertSame( 0 , $status , implode( PHP_EOL , $said ) ) ;
        }
        finally
        {
            exec( 'rm -rf ' . escapeshellarg( $directory ) ) ;
        }
    }
}
