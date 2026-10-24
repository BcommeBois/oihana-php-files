<?php

namespace tests\oihana\files\openssl;

use oihana\files\openssl\enums\EncryptionFormat;
use oihana\files\openssl\OpenSSLFileEncryption;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function oihana\files\openssl\kdfIsAvailable;

/**
 * The V3 on-disk format: that it round-trips whatever the size, and that the five
 * rewrites a per-block tag alone would not catch are all refused.
 *
 * Every test here writes with the smallest accepted block size
 * ({@see EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT}, 64 KiB) so that a 200 kB payload
 * is a four-frame file. The attacks need several frames to exist at all, and at this
 * size the whole suite stays in milliseconds.
 */
class OpenSSLFileEncryptionV3Test extends TestCase
{
    private string $testDir ;
    private string $plainFile ;
    private string $encryptedFile ;
    private string $decryptedFile ;
    private string $passphrase = 'secret' ;
    private int    $blockSize ;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/OpenSSLFileEncryptionV3Test' ;

        if ( !is_dir( $this->testDir ) )
        {
            mkdir( $this->testDir , 0777 , true ) ;
        }

        $this->plainFile     = $this->testDir . '/payload.bin' ;
        $this->encryptedFile = $this->testDir . '/payload.bin.enc' ;
        $this->decryptedFile = $this->testDir . '/payload.out' ;
        $this->blockSize     = 1 << EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT ;
    }

    protected function tearDown(): void
    {
        foreach ( glob( $this->testDir . '/*' ) ?: [] as $file )
        {
            is_dir( $file ) ? rmdir( $file ) : unlink( $file ) ;
        }

        if ( is_dir( $this->testDir ) )
        {
            rmdir( $this->testDir ) ;
        }
    }

    private function crypto( ?int $exponent = null , ?string $passphrase = null ) :OpenSSLFileEncryption
    {
        return new OpenSSLFileEncryption
        (
            $passphrase ?? $this->passphrase ,
            EncryptionFormat::LEGACY_CIPHER ,
            null ,
            $exponent ?? EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT
        ) ;
    }

    /**
     * Writes `$size` pseudo-random bytes and returns the path.
     */
    private function payload( int $size ) :string
    {
        file_put_contents( $this->plainFile , $size === 0 ? '' : random_bytes( $size ) ) ;

        return $this->plainFile ;
    }

    /**
     * Splits an encrypted file the way the reader does: the header, then one entry per
     * frame. Frame boundaries come from the exponent the file itself declares.
     *
     * @return array{0:string,1:array<int,string>}
     */
    private function split( string $path ) :array
    {
        $data   = file_get_contents( $path ) ;
        $header = substr( $data , 0 , EncryptionFormat::V3_HEADER_LENGTH ) ;
        $rest   = substr( $data , EncryptionFormat::V3_HEADER_LENGTH ) ;

        $full   = ( 1 << ord( $header[ 6 ] ) ) + EncryptionFormat::GCM_TAG_LENGTH ;
        $frames = [] ;

        while ( strlen( $rest ) > $full )
        {
            $frames[] = substr( $rest , 0 , $full ) ;
            $rest     = substr( $rest , $full ) ;
        }

        $frames[] = $rest ;

        return [ $header , $frames ] ;
    }

    /**
     * @param array<int,string> $frames
     */
    private function rebuild( string $path , string $header , array $frames ) :void
    {
        file_put_contents( $path , $header . implode( '' , $frames ) ) ;
    }

    /**
     * An instance that reads as though `ext-sodium` were absent.
     */
    private function cryptoWithoutSodium() :OpenSSLFileEncryption
    {
        return new class( $this->passphrase , EncryptionFormat::LEGACY_CIPHER , null , EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT ) extends OpenSSLFileEncryption
        {
            protected function isKdfAvailable( int $kdfAlgorithm ) : bool
            {
                return kdfIsAvailable( $kdfAlgorithm , false ) ;
            }
        } ;
    }

    private function expectGenericCryptoFailure() :void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Decryption failed due to incorrect passphrase or corrupted data.' ) ;
    }

    // ---------------------------------------------------------------------
    // Round trips
    // ---------------------------------------------------------------------

    public function testSeveralFramesRoundTrip(): void
    {
        $input = $this->payload( 200_000 ) ;

        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        [ , $frames ] = $this->split( $this->encryptedFile ) ;
        $this->assertCount( 4 , $frames , '200 000 bytes in 64 KiB blocks is four frames' ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;

        $this->assertSame
        (
            file_get_contents( $input ) ,
            file_get_contents( $this->decryptedFile )
        ) ;
    }

    /**
     * An empty file still carries one frame — an empty block and its tag. Were it just
     * a header, stripping every frame off a one-block file would produce a file that
     * decrypts to nothing, in silence, which no per-block tag could catch.
     */
    public function testEmptyFileRoundTripsAndStillCarriesOneFrame(): void
    {
        $input  = $this->payload( 0 ) ;
        $crypto = $this->crypto() ;

        $crypto->encrypt( $input , $this->encryptedFile ) ;

        $this->assertSame
        (
            EncryptionFormat::V3_HEADER_LENGTH + EncryptionFormat::GCM_TAG_LENGTH ,
            filesize( $this->encryptedFile ) ,
            'An empty file is a header plus one empty, sealed frame'
        ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;

        $this->assertSame( '' , file_get_contents( $this->decryptedFile ) ) ;
    }

    public static function sizeProvider() :array
    {
        $block = 1 << EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT ;

        return
        [
            'one byte'               => [ 1 ] ,
            'one byte short of a block' => [ $block - 1 ] ,
            'exactly one block'      => [ $block ] ,
            'one block plus a byte'  => [ $block + 1 ] ,
            'exactly two blocks'     => [ $block * 2 ] ,
        ] ;
    }

    /**
     * The sizes where the final-block flag is decided wrong by the obvious
     * implementation: `fread()` returning a full block leaves `feof()` false, so a
     * payload of exactly one or two blocks would be sealed as non-final and then read
     * as final. Hence a flag computed from the byte count, and these cases.
     *
     */
    #[DataProvider('sizeProvider')]
    public function testBlockBoundarySizesRoundTrip( int $size ): void
    {
        $input  = $this->payload( $size ) ;
        $crypto = $this->crypto() ;

        $crypto->encrypt( $input , $this->encryptedFile ) ;
        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;

        $this->assertSame
        (
            file_get_contents( $input ) ,
            file_get_contents( $this->decryptedFile ) ,
            sprintf( '%d bytes must come back unchanged' , $size )
        ) ;
    }

    /**
     * The reader must cut frames on the block size **the file declares**, not on its
     * own default — otherwise every file written with another block size becomes
     * unreadable the day the default changes.
     */
    public function testAFileIsReadWithTheBlockSizeItDeclares(): void
    {
        $input = $this->payload( 200_000 ) ;

        $this->crypto( 17 )->encrypt( $input , $this->encryptedFile ) ;

        $header = file_get_contents( $this->encryptedFile ) ;
        $this->assertSame( 17 , ord( $header[ 6 ] ) ) ;

        $this->crypto( 20 )->decrypt( $this->encryptedFile , $this->decryptedFile ) ;

        $this->assertSame
        (
            file_get_contents( $input ) ,
            file_get_contents( $this->decryptedFile )
        ) ;
    }

    public function testWrongPassphraseIsRefused(): void
    {
        $input = $this->payload( 200_000 ) ;

        $this->crypto()->encrypt( $input , $this->encryptedFile ) ;

        $this->expectGenericCryptoFailure() ;

        $this->crypto( null , 'not-the-passphrase' )->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    // ---------------------------------------------------------------------
    // The rewrites a per-block tag alone would not catch
    // ---------------------------------------------------------------------

    public function testATamperedMiddleBlockIsDetected(): void
    {
        $input  = $this->payload( 200_000 ) ;
        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        [ $header , $frames ] = $this->split( $this->encryptedFile ) ;

        $frames[ 1 ][ 100 ] = chr( ord( $frames[ 1 ][ 100 ] ) ^ 0xFF ) ;

        $this->rebuild( $this->encryptedFile , $header , $frames ) ;

        $this->expectGenericCryptoFailure() ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    public function testTwoSwappedFramesAreDetected(): void
    {
        $input  = $this->payload( 200_000 ) ;
        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        [ $header , $frames ] = $this->split( $this->encryptedFile ) ;

        [ $frames[ 0 ] , $frames[ 1 ] ] = [ $frames[ 1 ] , $frames[ 0 ] ] ;

        $this->rebuild( $this->encryptedFile , $header , $frames ) ;

        $this->expectGenericCryptoFailure() ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    public function testAFrameDroppedFromTheMiddleIsDetected(): void
    {
        $input  = $this->payload( 200_000 ) ;
        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        [ $header , $frames ] = $this->split( $this->encryptedFile ) ;

        unset( $frames[ 1 ] ) ;

        $this->rebuild( $this->encryptedFile , $header , $frames ) ;

        $this->expectGenericCryptoFailure() ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    /**
     * The one the final-block flag exists for. Remove the last frame and the file is
     * still a sequence of intact, correctly numbered frames — every tag would verify.
     * What gives it away is that the new last frame was sealed as *not* final.
     */
    public function testTheFinalFrameBeingRemovedIsDetected(): void
    {
        $input  = $this->payload( $this->blockSize * 2 ) ;
        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        [ $header , $frames ] = $this->split( $this->encryptedFile ) ;

        $this->assertCount( 2 , $frames ) ;
        array_pop( $frames ) ;

        $this->rebuild( $this->encryptedFile , $header , $frames ) ;

        $this->expectGenericCryptoFailure() ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    public function testATruncatedFinalFrameIsDetected(): void
    {
        $input  = $this->payload( 200_000 ) ;
        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        [ $header , $frames ] = $this->split( $this->encryptedFile ) ;

        $last            = count( $frames ) - 1 ;
        $frames[ $last ] = substr( $frames[ $last ] , 0 , -5 ) ;

        $this->rebuild( $this->encryptedFile , $header , $frames ) ;

        $this->expectGenericCryptoFailure() ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    /**
     * Two files encrypted with the same passphrase, one's header on the other's
     * frames. The per-file salt and nonce prefix are part of what every tag covers,
     * so the splice does not open.
     */
    public function testTwoFilesSplicedTogetherAreDetected(): void
    {
        $crypto = $this->crypto() ;

        $first  = $this->testDir . '/first.enc' ;
        $second = $this->testDir . '/second.enc' ;

        $crypto->encrypt( $this->payload( 200_000 ) , $first ) ;
        $crypto->encrypt( $this->payload( 200_000 ) , $second ) ;

        [ $headerOfFirst ]      = $this->split( $first ) ;
        [ , $framesOfSecond ]   = $this->split( $second ) ;

        $this->rebuild( $this->encryptedFile , $headerOfFirst , $framesOfSecond ) ;

        $this->expectGenericCryptoFailure() ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    // ---------------------------------------------------------------------
    // Malformed headers and frames, refused before they cost anything
    // ---------------------------------------------------------------------

    public function testAFileReducedToItsHeaderIsRefused(): void
    {
        $input  = $this->payload( 1 ) ;
        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        [ $header ] = $this->split( $this->encryptedFile ) ;
        file_put_contents( $this->encryptedFile , $header ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'V3 file is shorter than a header and one frame' ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    public function testAFrameShorterThanItsTagIsRefused(): void
    {
        $input  = $this->payload( $this->blockSize + 1_000 ) ;
        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        // Keep the whole first frame, and 8 bytes of the second: too few to be a tag.
        $keep = EncryptionFormat::V3_HEADER_LENGTH + $this->blockSize + EncryptionFormat::GCM_TAG_LENGTH + 8 ;
        file_put_contents( $this->encryptedFile , substr( file_get_contents( $this->encryptedFile ) , 0 , $keep ) ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'shorter than its authentication tag' ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    public static function hostileExponentProvider() :array
    {
        return
        [
            'a 2^255-byte allocation' => [ 255 ] ,
            'one-byte blocks'         => [ 0 ] ,
            'just under the floor'    => [ EncryptionFormat::MIN_BLOCK_SIZE_EXPONENT - 1 ] ,
            'just over the ceiling'   => [ EncryptionFormat::MAX_BLOCK_SIZE_EXPONENT + 1 ] ,
        ] ;
    }

    /**
     * The exponent is one byte of a header nobody has authenticated yet, so it is
     * bounded before it is shifted — and the refusal has to come before any allocation.
     *
     */
    #[DataProvider('hostileExponentProvider')]
    public function testAHostileBlockSizeExponentIsRefused( int $exponent ): void
    {
        $input  = $this->payload( 1 ) ;
        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        $data      = file_get_contents( $this->encryptedFile ) ;
        $data[ 6 ] = chr( $exponent ) ;
        file_put_contents( $this->encryptedFile , $data ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'declares an unusable block size' ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    /**
     * The header names the KDF so the reader does not have to guess — it does not make
     * that KDF available. A V3 archive written where `ext-sodium` is loaded, read where
     * it is not, must say which extension is missing rather than blame the passphrase.
     */
    public function testAnArgon2idFileWithoutSodiumNamesTheMissingExtension(): void
    {
        if ( !function_exists('sodium_crypto_pwhash') )
        {
            $this->markTestSkipped( 'Writing an Argon2id file needs the extension this test then pretends is absent.' ) ;
        }

        $this->crypto()->encrypt( $this->payload( 200_000 ) , $this->encryptedFile ) ;

        $this->assertSame
        (
            EncryptionFormat::KDF_ARGON2ID ,
            ord( file_get_contents( $this->encryptedFile )[ 5 ] ) ,
            'The fixture is only meaningful if the file really names Argon2id'
        ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'requires the sodium extension' ) ;

        $this->cryptoWithoutSodium()->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    /**
     * A file bearing our magic and a version we do not know was written by a newer
     * release. Running it through the V1 path — which has no integrity check — would
     * emit whatever AES-CBC made of it, so it is refused by name instead.
     */
    public function testAnUnknownFormatVersionIsRefusedRatherThanGuessed(): void
    {
        $input  = $this->payload( 1 ) ;
        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        $data      = file_get_contents( $this->encryptedFile ) ;
        $data[ 4 ] = chr( 9 ) ;
        file_put_contents( $this->encryptedFile , $data ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'unsupported format version 9' ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    public function testNothingIsLeftBehindWhenAFrameFailsItsTag(): void
    {
        $input  = $this->payload( 200_000 ) ;
        $crypto = $this->crypto() ;
        $crypto->encrypt( $input , $this->encryptedFile ) ;

        [ $header , $frames ] = $this->split( $this->encryptedFile ) ;

        $last            = count( $frames ) - 1 ;
        $frames[ $last ] = substr( $frames[ $last ] , 0 , -5 ) ;

        $this->rebuild( $this->encryptedFile , $header , $frames ) ;

        try
        {
            $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
            $this->fail( 'A truncated final frame must not decrypt.' ) ;
        }
        catch ( RuntimeException )
        {
            // A streaming read writes as it goes, so the frames that did verify are
            // already on disk when the last one fails. Neither the destination nor the
            // partial file may survive: half a plaintext looks exactly like a whole one.
            $this->assertFileDoesNotExist( $this->decryptedFile ) ;
            $this->assertFileDoesNotExist( $this->decryptedFile . '.part' ) ;
        }
    }

    /**
     * The payload is written beside the destination, so the destination's own name
     * being free is not enough — the partial name has to be free too. A directory
     * sitting where the partial file goes is the cheapest way to stage that.
     */
    public function testEncryptReportsAPartialFileItCannotCreate(): void
    {
        $input = $this->payload( 1 ) ;

        mkdir( $this->encryptedFile . '.part' ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Encryption failed, file write failed' ) ;

        $this->crypto()->encrypt( $input , $this->encryptedFile ) ;
    }

    public function testDecryptReportsAPartialFileItCannotCreate(): void
    {
        $crypto = $this->crypto() ;
        $crypto->encrypt( $this->payload( 1 ) , $this->encryptedFile ) ;

        mkdir( $this->decryptedFile . '.part' ) ;

        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessage( 'Decryption failed, file write failed' ) ;

        $crypto->decrypt( $this->encryptedFile , $this->decryptedFile ) ;
    }

    // ---------------------------------------------------------------------
    // isEncryptedFile(), on the first bytes only
    // ---------------------------------------------------------------------

    public function testIsEncryptedFileRecognisesAV3File(): void
    {
        $crypto = $this->crypto() ;
        $crypto->encrypt( $this->payload( 200_000 ) , $this->encryptedFile ) ;

        $this->assertTrue( $crypto->isEncryptedFile( $this->encryptedFile ) ) ;
    }

    public function testIsEncryptedFileRefusesAV3FileWithoutAFrame(): void
    {
        $crypto = $this->crypto() ;
        $crypto->encrypt( $this->payload( 1 ) , $this->encryptedFile ) ;

        [ $header ] = $this->split( $this->encryptedFile ) ;
        file_put_contents( $this->encryptedFile , $header ) ;

        $this->assertFalse( $crypto->isEncryptedFile( $this->encryptedFile ) ) ;
    }

    public function testIsEncryptedFileAnswersTrueForANewerVersionOfOurOwnFormat(): void
    {
        $crypto = $this->crypto() ;
        $crypto->encrypt( $this->payload( 1 ) , $this->encryptedFile ) ;

        $data      = file_get_contents( $this->encryptedFile ) ;
        $data[ 4 ] = chr( 9 ) ;
        file_put_contents( $this->encryptedFile , $data ) ;

        $this->assertTrue
        (
            $crypto->isEncryptedFile( $this->encryptedFile ) ,
            'The file is ours, it is simply newer than this reader'
        ) ;
    }

    public function testIsEncryptedFileRefusesAMissingFile(): void
    {
        $this->assertFalse( $this->crypto()->isEncryptedFile( $this->testDir . '/absent.enc' ) ) ;
    }

    public function testIsEncryptedFileRefusesPlainText(): void
    {
        $text = $this->testDir . '/notes.txt' ;
        file_put_contents( $text , str_repeat( 'plain ASCII content, nothing encrypted here. ' , 40 ) ) ;

        $this->assertFalse( $this->crypto()->isEncryptedFile( $text ) ) ;
    }

    public function testIsEncryptedFileRefusesAFileShorterThanAnIv(): void
    {
        $short = $this->testDir . '/short.bin' ;
        file_put_contents( $short , "\x01\x02\x03" ) ;

        $this->assertFalse( $this->crypto()->isEncryptedFile( $short ) ) ;
    }

    public function testIsEncryptedFileRefusesAZeroFilledHead(): void
    {
        $zeros = $this->testDir . '/zeros.bin' ;
        file_put_contents( $zeros , str_repeat( "\0" , 64 ) ) ;

        $this->assertFalse( $this->crypto()->isEncryptedFile( $zeros ) ) ;
    }
}
