<?php

namespace tests\oihana\files\toml;

use Devium\Toml\Toml;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use function oihana\files\toml\setTomlValue;

#[CoversFunction('oihana\files\toml\setTomlValue')]
final class SetTomlValueTest extends TestCase
{
    /**
     * The same key names recur across several tables: the shape that makes an unanchored
     * replacement overwrite unrelated credentials.
     */
    private const string CONFIG = <<<'TOML'
        [server]
        domain    = "example.com"
        url       = "https://www.example.com"

        [database]
        host    = "127.0.0.1"
        name    = "example"
        pass    = "db-secret" # strong unique password
        prefix  = "app_"

        [smtp]
        host = "smtp.example.com"
        pass = "smtp-secret"

        [mysql]
        host     = "127.0.0.1"
        password = 'root-secret'
        TOML ;

    public function testReplacesOnlyTheKeyOfTheTargetSection() :void
    {
        $result = setTomlValue( self::CONFIG , 'database' , 'pass' , 'new-db-secret' ) ;

        $this->assertStringContainsString( 'pass    = "new-db-secret"' , $result ) ;
        $this->assertStringContainsString( 'pass = "smtp-secret"'      , $result ) ;
        $this->assertStringContainsString( "password = 'root-secret'"  , $result ) ;
    }

    public function testReplacesTheKeyOfALaterSectionOnly() :void
    {
        $result = setTomlValue( self::CONFIG , 'smtp' , 'host' , 'mail.example.org' ) ;

        $this->assertStringContainsString( 'host    = "127.0.0.1"'      , $result ) ;
        $this->assertStringContainsString( 'host = "mail.example.org"'  , $result ) ;
        $this->assertStringContainsString( 'host     = "127.0.0.1"'     , $result ) ;
    }

    public function testTheSectionNameIgnoresCase() :void
    {
        $result = setTomlValue( self::CONFIG , 'DataBase' , 'name' , 'renamed' ) ;

        $this->assertStringContainsString( 'name    = "renamed"' , $result ) ;
    }

    public function testKeepsTheAlignmentAndTheTrailingComment() :void
    {
        $result = setTomlValue( self::CONFIG , 'database' , 'pass' , 'x' ) ;

        $this->assertStringContainsString( 'pass    = "x" # strong unique password' , $result ) ;
    }

    public function testReplacesASingleQuotedValue() :void
    {
        $result = setTomlValue( self::CONFIG , 'mysql' , 'password' , 'rotated' ) ;

        $this->assertStringContainsString( 'password = "rotated"' , $result ) ;
        $this->assertStringNotContainsString( 'root-secret' , $result ) ;
    }

    public function testReplacesAValueHoldingAnEscapedQuote() :void
    {
        $config = "[database]\npass = \"a\\\"b\" # kept\n" ;

        $result = setTomlValue( $config , 'database' , 'pass' , 'c' ) ;

        $this->assertSame( "[database]\npass = \"c\" # kept\n" , $result ) ;
    }

    public function testEscapesQuotesAndBackslashes() :void
    {
        $result = setTomlValue( self::CONFIG , 'database' , 'pass' , 'a"b\\c' ) ;

        $this->assertStringContainsString( 'pass    = "a\\"b\\\\c"' , $result ) ;
    }

    /**
     * A password may hold `$1` or `\1`. Used as a preg_replace() replacement, they would become
     * backreferences, and a different password than the one asked for would be written.
     */
    public function testDoesNotReadBackreferenceSyntax() :void
    {
        $result = setTomlValue( self::CONFIG , 'database' , 'pass' , 'pa$1ss\\1' ) ;

        $this->assertStringContainsString( 'pass    = "pa$1ss\\\\1"' , $result ) ;
    }

    public function testAddsAMissingKeyAtTheEndOfItsSection() :void
    {
        $result = setTomlValue( self::CONFIG , 'database' , 'collate' , 'utf8mb4_unicode_ci' ) ;

        $this->assertStringContainsString( "prefix  = \"app_\"\ncollate = \"utf8mb4_unicode_ci\"\n\n[smtp]" , $result ) ;
    }

    public function testAddsAMissingKeyToTheLastSection() :void
    {
        $result = setTomlValue( self::CONFIG , 'mysql' , 'port' , '3306' ) ;

        $this->assertStringEndsWith( "password = 'root-secret'\nport = \"3306\"" , $result ) ;
    }

    public function testAddsAMissingSectionAtTheEnd() :void
    {
        $result = setTomlValue( self::CONFIG , 'maintenance' , 'flag' , '.in-maintenance' ) ;

        $this->assertStringEndsWith( "password = 'root-secret'\n\n[maintenance]\nflag = \".in-maintenance\"\n" , $result ) ;
    }

    public function testTargetsTheRootTableWhenTheSectionIsNull() :void
    {
        $config = "title = \"Old\"\n\n[server]\ntitle = \"Server\"\n" ;

        $result = setTomlValue( $config , null , 'title' , 'New' ) ;

        $this->assertSame( "title = \"New\"\n\n[server]\ntitle = \"Server\"\n" , $result ) ;
    }

    public function testAddsAMissingRootKeyAboveTheFirstSection() :void
    {
        $config = "title = \"Old\"\n\n[server]\nname = \"x\"\n" ;

        $result = setTomlValue( $config , null , 'name' , 'root' ) ;

        $this->assertSame( "title = \"Old\"\nname = \"root\"\n\n[server]\nname = \"x\"\n" , $result ) ;
    }

    public function testAddsARootKeyToADocumentWithoutSections() :void
    {
        $this->assertSame( "name = \"root\"\n" , setTomlValue( '' , null , 'name' , 'root' ) ) ;
    }

    public function testKeepsWindowsLineEndings() :void
    {
        $config = "[database]\r\nhost = \"a\"\r\n\r\n[smtp]\r\nhost = \"b\"\r\n" ;

        $this->assertSame
        (
            "[database]\r\nhost = \"a\"\r\nuser = \"u\"\r\n\r\n[smtp]\r\nhost = \"b\"\r\n" ,
            setTomlValue( $config , 'database' , 'user' , 'u' )
        ) ;
    }

    public function testLeavesTheOtherLinesUntouched() :void
    {
        $result = setTomlValue( self::CONFIG , 'database' , 'name' , 'example_staging' ) ;

        $this->assertSame( str_replace( '"example"' , '"example_staging"' , self::CONFIG ) , $result ) ;
    }

    public function testTheResultIsStillValidToml() :void
    {
        $result = setTomlValue( self::CONFIG , 'database' , 'pass' , "q\"b\\s\nnew" ) ;
        $result = setTomlValue( $result , 'cache' , 'path' , '/var/cache' ) ;

        $document = Toml::decode( $result , true ) ;

        $this->assertSame( "q\"b\\s\nnew" , $document[ 'database' ][ 'pass' ] ) ;
        $this->assertSame( 'smtp-secret'  , $document[ 'smtp' ][ 'pass' ] ) ;
        $this->assertSame( '/var/cache'   , $document[ 'cache' ][ 'path' ] ) ;
    }
}
