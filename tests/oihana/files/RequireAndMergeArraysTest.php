<?php

namespace oihana\files ;

use InvalidArgumentException;
use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RequireAndMergeArraysTest extends TestCase
{
    private string $tmpDir ;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/oihana/files/require_merge_test_' . uniqid() ;
        mkdir( $this->tmpDir , 0777 , true ) ;
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tmpDir ) ;
    }

    private function writeArrayFile( string $name , array $data ) : string
    {
        $path = $this->tmpDir . '/' . $name ;
        file_put_contents( $path , "<?php\nreturn " . var_export( $data , true ) . " ;\n" ) ;
        return $path ;
    }

    public function testReturnsEmptyArrayWithEmptyInput(): void
    {
        $this->assertSame( [] , requireAndMergeArrays( [] ) ) ;
    }

    public function testMergesDeepByDefault(): void
    {
        $a = $this->writeArrayFile('a.php' , [ 'app' => [ 'debug' => false , 'tz' => 'UTC' ] ] ) ;
        $b = $this->writeArrayFile('b.php' , [ 'app' => [ 'debug' => true ] ] ) ;

        $result = requireAndMergeArrays( [ $a , $b ] ) ;

        $this->assertSame( true  , $result[ 'app' ][ 'debug' ] ) ;
        $this->assertSame( 'UTC' , $result[ 'app' ][ 'tz' ] ) ;
    }

    public function testMergesShallowWhenRecursiveFalse(): void
    {
        $a = $this->writeArrayFile('a.php' , [ 'app' => [ 'debug' => false , 'tz' => 'UTC' ] ] ) ;
        $b = $this->writeArrayFile('b.php' , [ 'app' => [ 'debug' => true ] ] ) ;

        $result = requireAndMergeArrays( [ $a , $b ] , false ) ;

        // Shallow merge: second file overwrites the whole 'app' subarray
        $this->assertSame( true , $result[ 'app' ][ 'debug' ] ) ;
        $this->assertArrayNotHasKey( 'tz' , $result[ 'app' ] ) ;
    }

    public function testThrowsWhenPathIsNotString(): void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'non-empty string' ) ;

        /** @phpstan-ignore-next-line — intentional invalid input */
        requireAndMergeArrays( [ 42 ] ) ;
    }

    public function testThrowsWhenPathIsEmptyString(): void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'non-empty string' ) ;

        requireAndMergeArrays( [ '   ' ] ) ;
    }

    public function testThrowsWhenFileMissing(): void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'was not found' ) ;

        requireAndMergeArrays( [ $this->tmpDir . '/missing.php' ] ) ;
    }

    public function testThrowsWhenExtensionIsNotPhp(): void
    {
        $bad = $this->tmpDir . '/config.txt' ;
        file_put_contents( $bad , "<?php\nreturn [] ;\n" ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( '.php expected' ) ;

        requireAndMergeArrays( [ $bad ] ) ;
    }

    public function testAcceptsUppercasePhpExtension(): void
    {
        $upper = $this->tmpDir . '/CONFIG.PHP' ;
        file_put_contents( $upper , "<?php\nreturn [ 'ok' => true ] ;\n" ) ;

        $result = requireAndMergeArrays( [ $upper ] ) ;
        $this->assertSame( [ 'ok' => true ] , $result ) ;
    }

    public function testThrowsWhenFileDoesNotReturnArray(): void
    {
        $bad = $this->writeArrayFile('bad.php' , [] ) ;
        // overwrite with a non-array return
        file_put_contents( $bad , "<?php\nreturn 'not an array' ;\n" ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'did not return an array' ) ;

        requireAndMergeArrays( [ $bad ] ) ;
    }

    public function testAllowedBaseAcceptsFilesUnderIt(): void
    {
        $a = $this->writeArrayFile('a.php' , [ 'ok' => 1 ] ) ;

        $result = requireAndMergeArrays( [ $a ] , true , $this->tmpDir ) ;
        $this->assertSame( [ 'ok' => 1 ] , $result ) ;
    }

    public function testAllowedBaseRefusesFilesOutsideIt(): void
    {
        // file is in $this->tmpDir, but we declare a sibling dir as allowed base
        $a = $this->writeArrayFile('a.php' , [ 'ok' => 1 ] ) ;

        $sibling = sys_get_temp_dir() . '/oihana/files/sibling_' . uniqid() ;
        mkdir( $sibling , 0777 , true ) ;

        try {
            $this->expectException( RuntimeException::class ) ;
            $this->expectExceptionMessage( 'outside the allowed base' ) ;

            requireAndMergeArrays( [ $a ] , true , $sibling ) ;
        }
        finally {
            rmdir( $sibling ) ;
        }
    }

    public function testThrowsWhenAllowedBaseIsNotADirectory(): void
    {
        $this->expectException( InvalidArgumentException::class ) ;
        $this->expectExceptionMessage( 'is not a valid directory' ) ;

        requireAndMergeArrays( [] , true , '/path/that/does/not/exist' ) ;
    }

    public function testMaxBytesNullKeepsLegacyBehaviour(): void
    {
        $a = $this->writeArrayFile('a.php' , [ 'ok' => 1 ] ) ;

        $result = requireAndMergeArrays( [ $a ] , true , null , null ) ;
        $this->assertSame( [ 'ok' => 1 ] , $result ) ;
    }

    public function testMaxBytesUnderLimitAllowsLoad(): void
    {
        $a = $this->writeArrayFile('a.php' , [ 'ok' => 1 ] ) ;

        $result = requireAndMergeArrays( [ $a ] , true , null , 4096 ) ;
        $this->assertSame( [ 'ok' => 1 ] , $result ) ;
    }

    public function testMaxBytesExceededThrowsAndDoesNotInclude(): void
    {
        $a = $this->writeArrayFile('a.php' , [ 'ok' => 1 ] ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'exceeds maximum' ) ;

        requireAndMergeArrays( [ $a ] , true , null , 4 ) ;
    }
}
