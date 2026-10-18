<?php

namespace tests\oihana\files ;

use PHPUnit\Framework\Attributes\CoversFunction ;
use PHPUnit\Framework\TestCase ;

use oihana\files\enums\FileMimeType ;
use oihana\files\enums\MimeTypeAlias ;

use function oihana\files\normalizeMimeType ;

/**
 * The reference name of a format, whichever name it is given under.
 */
#[CoversFunction( 'oihana\files\normalizeMimeType' )]
final class NormalizeMimeTypeTest extends TestCase
{
    protected function setUp() :void
    {
        FileMimeType::resetCaches() ;
    }

    /**
     * The names finfo reports for a WAV, a raw AAC stream and an XML document, where the
     * catalogue knows another one.
     */
    public function testAnAliasReportedByFinfoBecomesItsReferenceName() :void
    {
        $this->assertSame( FileMimeType::WAV , normalizeMimeType( 'audio/x-wav'         ) ) ;
        $this->assertSame( FileMimeType::AAC , normalizeMimeType( 'audio/x-hx-aac-adts' ) ) ;
        $this->assertSame( FileMimeType::XML , normalizeMimeType( 'text/xml'            ) ) ;
    }

    /**
     * A browser declares a voice recording as `audio/webm` ; finfo reports `video/webm` for the
     * same container.
     */
    public function testAVoiceRecordingMeetsTheWebmContainer() :void
    {
        $this->assertSame( FileMimeType::WEBM , normalizeMimeType( 'audio/webm' ) ) ;
    }

    public function testAnOlderNameBecomesItsReferenceName() :void
    {
        $this->assertSame( FileMimeType::WAV  , normalizeMimeType( 'audio/wave'         ) ) ;
        $this->assertSame( FileMimeType::WAV  , normalizeMimeType( 'audio/vnd.wave'     ) ) ;
        $this->assertSame( FileMimeType::AAC  , normalizeMimeType( 'audio/x-aac'        ) ) ;
        $this->assertSame( FileMimeType::FLAC , normalizeMimeType( 'audio/x-flac'       ) ) ;
        $this->assertSame( FileMimeType::BMP  , normalizeMimeType( 'image/x-ms-bmp'     ) ) ;
        $this->assertSame( FileMimeType::GZ   , normalizeMimeType( 'application/x-gzip' ) ) ;
    }

    public function testTheCaseAndTheParametersDoNotCount() :void
    {
        $this->assertSame( FileMimeType::XML , normalizeMimeType( 'Text/XML; charset=utf-8' ) ) ;
        $this->assertSame( FileMimeType::WAV , normalizeMimeType( ' AUDIO/WAV ; codecs=1 '  ) ) ;
    }

    public function testAReferenceNameStaysAsItIs() :void
    {
        $this->assertSame( FileMimeType::PNG , normalizeMimeType( FileMimeType::PNG ) ) ;
        $this->assertSame( FileMimeType::WAV , normalizeMimeType( FileMimeType::WAV ) ) ;
    }

    public function testANameWithoutAliasComesBackCleaned() :void
    {
        $this->assertSame( 'application/x-unknown' , normalizeMimeType( 'Application/X-Unknown; v=2' ) ) ;
        $this->assertSame( '' , normalizeMimeType( '' ) ) ;
    }

    /**
     * Every alias must lead to a name the catalogue knows, or it would still find no extension.
     */
    public function testEveryAliasLeadsToANameTheCatalogueKnows() :void
    {
        foreach( MimeTypeAlias::ALIASES as $alias => $reference )
        {
            $this->assertNotNull( FileMimeType::getConstant( $reference ) , "$alias leads to $reference, unknown to FileMimeType" ) ;
        }
    }

    /**
     * An alias that the catalogue knew under its own name would be read two ways ; and an alias
     * written with capitals would never be matched, the name being lower-cased first.
     */
    public function testEveryAliasIsALowerCaseNameTheCatalogueDoesNotKnow() :void
    {
        foreach( array_keys( MimeTypeAlias::ALIASES ) as $alias )
        {
            $this->assertSame( strtolower( $alias ) , $alias , "$alias must be lower-case" ) ;
            $this->assertNull( FileMimeType::getConstant( $alias ) , "$alias is already a name of FileMimeType" ) ;
        }
    }
}
