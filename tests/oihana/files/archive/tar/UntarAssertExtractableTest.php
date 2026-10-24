<?php

namespace tests\oihana\files\archive\tar;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\archive\tar\untarAssertExtractable;

/**
 * The guards of `untar()`, asked of a listing rather than of an engine — which is the
 * point: enforced once, they cannot drift apart between the two engines.
 */
class UntarAssertExtractableTest extends TestCase
{
    private string $root ;

    protected function setUp() :void
    {
        $this->root = sys_get_temp_dir() . '/oihana-untar-guards-' . bin2hex( random_bytes( 6 ) ) ;
        mkdir( $this->root , 0777 , true ) ;
    }

    protected function tearDown() :void
    {
        foreach ( glob( $this->root . '/*' ) ?: [] as $file )
        {
            unlink( $file ) ;
        }

        rmdir( $this->root ) ;
    }

    /**
     * @return array<int, array{name: string, size: int, directory: bool}>
     */
    private function entries() :array
    {
        return
        [
            [ 'name' => 'site/'          , 'size' => 0    , 'directory' => true  ] ,
            [ 'name' => 'site/index.php' , 'size' => 1200 , 'directory' => false ] ,
            [ 'name' => 'site/un deux.txt' , 'size' => 800  , 'directory' => false ] ,
        ] ;
    }

    public function testTheListReturnedHoldsTheFilesAndNotTheDirectories(): void
    {
        $this->assertSame
        (
            [ 'site/index.php' , 'site/un deux.txt' ] ,
            untarAssertExtractable( $this->entries() , $this->root )
        ) ;
    }

    public function testAnEmptyArchiveIsExtractable(): void
    {
        $this->assertSame( [] , untarAssertExtractable( [] , $this->root ) ) ;
    }

    public function testANameEscapingTheOutputDirectoryIsRefused(): void
    {
        $entries = [ [ 'name' => '../escaped.txt' , 'size' => 10 , 'directory' => false ] ] ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Path traversal attempt detected' ) ;

        untarAssertExtractable( $entries , $this->root ) ;
    }

    /**
     * A directory entry is refused too, although it is skipped afterwards: `../` as a
     * directory is the same escape.
     */
    public function testATraversingDirectoryIsRefusedAsWell(): void
    {
        $entries = [ [ 'name' => '../elsewhere/' , 'size' => 0 , 'directory' => true ] ] ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Path traversal attempt detected' ) ;

        untarAssertExtractable( $entries , $this->root ) ;
    }

    public function testAnExistingTargetIsRefusedWhenOverwriteIsOff(): void
    {
        mkdir( $this->root . '/site' ) ;
        file_put_contents( $this->root . '/site/index.php' , 'already here' ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'already exists and overwrite is disabled' ) ;

        try
        {
            untarAssertExtractable( $this->entries() , $this->root , false ) ;
        }
        finally
        {
            unlink( $this->root . '/site/index.php' ) ;
            rmdir( $this->root . '/site' ) ;
        }
    }

    public function testAnExistingTargetIsAcceptedWhenOverwriteIsOn(): void
    {
        mkdir( $this->root . '/site' ) ;
        file_put_contents( $this->root . '/site/index.php' , 'already here' ) ;

        try
        {
            $this->assertCount( 2 , untarAssertExtractable( $this->entries() , $this->root , true ) ) ;
        }
        finally
        {
            unlink( $this->root . '/site/index.php' ) ;
            rmdir( $this->root . '/site' ) ;
        }
    }

    public function testTheTotalSizeIsCappedAcrossEntries(): void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'potential decompression bomb' ) ;

        // 1 200 + 800 = 2 000, and neither entry exceeds the cap on its own.
        untarAssertExtractable( $this->entries() , $this->root , true , 1_500 ) ;
    }

    public function testACapEqualToTheTotalPasses(): void
    {
        $this->assertCount( 2 , untarAssertExtractable( $this->entries() , $this->root , true , 2_000 ) ) ;
    }

    public function testACapOfZeroRefusesAnyContent(): void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'exceeds maximum 0 bytes' ) ;

        untarAssertExtractable( $this->entries() , $this->root , true , 0 ) ;
    }

    public function testNoCapMeansNoLimit(): void
    {
        $huge = [ [ 'name' => 'big.bin' , 'size' => PHP_INT_MAX , 'directory' => false ] ] ;

        $this->assertSame( [ 'big.bin' ] , untarAssertExtractable( $huge , $this->root , true , null ) ) ;
    }
}
