<?php

namespace oihana\files\archive\tar ;

use RuntimeException;

use oihana\files\enums\CompressionType;

/**
 * Reads the headers of an uncompressed tar archive and returns the link entries it holds.
 *
 * `PharData` is the only reader this library has when there is no GNU tar, and it cannot see a
 * link: a link entry comes back from its iterator as a regular file of size 0, with `isLink()`
 * answering `false`, and `extractTo()` writes an empty file in its place. Asking it is therefore
 * not an option, and the headers are read here instead — they are plain, fixed and documented.
 *
 * A compressed archive is read through its stream wrapper, which is the only way to see its
 * links at all: `PharData::decompress()` rewrites the archive to decompress it, and since it
 * cannot represent a link it writes a regular empty file in its place. The link is gone from the
 * `.tar` it produces, so the compressed stream has to be read before it, not after.
 *
 * Both kinds of link are reported, `'2'` (symbolic) and `'1'` (hard), along with GNU long names
 * (`'L'`, `'K'`) and the `path`/`linkpath` records of a pax header (`'x'`), since any of them can
 * carry the name or the target of the entry that follows.
 *
 * @param string      $tarFile     Path to a tar archive.
 * @param string|null $compression  Its compression, from {@see CompressionType}, or `null` when
 *                                  the archive is a plain `.tar`. Only gzip and bzip2 can be read
 *                                  here, since those are the two PHP has a stream wrapper for.
 *
 * @return array<int, array{name: string, target: string, hard: bool}> The link entries, in the
 *         order the archive holds them.
 *
 * @throws RuntimeException If the file cannot be read, is not a tar archive, or holds a block
 *                          that is not a header.
 *
 * @example
 * ```php
 * use function oihana\files\archive\tar\tarLinkEntries;
 *
 * foreach ( tarLinkEntries( '/tmp/site.tar' ) as $link )
 * {
 *     // [ 'name' => 'wp-content/uploads' , 'target' => '/var/data/uploads' , 'hard' => false ]
 * }
 * ```
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.6.0
 */
function tarLinkEntries( string $tarFile , ?string $compression = null ): array
{
    $wrapper = match ( $compression )
    {
        null                   => '' ,
        CompressionType::GZIP  => 'compress.zlib://' ,
        CompressionType::BZIP2 => 'compress.bzip2://' ,
        default                => throw new RuntimeException( sprintf
        (
            'tarLinkEntries() failed, %s is a %s archive and PHP has no stream wrapper to read one.' ,
            $tarFile ,
            $compression
        )) ,
    } ;

    if ( !is_file( $tarFile ) )
    {
        throw new RuntimeException( sprintf( 'tarLinkEntries() failed, could not open %s.' , $tarFile ) ) ;
    }

    $handle = @fopen( $wrapper . $tarFile , 'rb' ) ;

    if ( $handle === false )
    {
        throw new RuntimeException( sprintf( 'tarLinkEntries() failed, could not open %s.' , $tarFile ) ) ;
    }

    // A compressed stream cannot be seeked, so the data blocks are read and dropped there.

    $seekable = (bool) ( stream_get_meta_data( $handle )[ 'seekable' ] ?? false ) ;
    $first    = true ;

    $links     = [] ;
    $longName  = null ;
    $longLink  = null ;
    $paxName   = null ;
    $paxTarget = null ;

    try
    {
        while ( true )
        {
            $header = tarReadBlock( $handle ) ;

            if ( $header === null )
            {
                if ( $first )
                {
                    throw new RuntimeException( sprintf
                    (
                        'tarLinkEntries() failed, %s is shorter than one tar header, so it is not a tar archive.' ,
                        $tarFile
                    )) ;
                }

                // A truncated tail is where the archive ends in practice: tar pads with two zero
                // blocks, and a writer that stopped short has nothing more to say either.
                break ;
            }

            $first = false ;

            $name = trim( substr( $header , 0 , 100 ) , "\0" ) ;

            if ( $name === '' )
            {
                // The zero block that closes the archive.
                break ;
            }

            if ( trim( substr( $header , 257 , 5 ) , "\0 " ) !== 'ustar' )
            {
                throw new RuntimeException( sprintf
                (
                    'tarLinkEntries() failed, %s holds a block that is not a tar header, so its entries cannot be accounted for.' ,
                    $tarFile
                )) ;
            }

            $type   = substr( $header , 156 , 1 ) ;
            $size   = tarHeaderSize( $header ) ;
            $blocks = (int) ceil( $size / 512 ) ;

            // Only the three headers that describe the next entry have data worth reading. For
            // everything else the data is stepped over, so scanning a large archive costs a seek
            // per entry rather than a read of its contents.

            $carries = $type === 'L' || $type === 'K' || $type === 'x' ;
            $data    = '' ;

            if ( $blocks > 0 )
            {
                if ( $carries )
                {
                    $data = (string) fread( $handle , $blocks * 512 ) ;
                }
                else if ( $seekable )
                {
                    if ( fseek( $handle , $blocks * 512 , SEEK_CUR ) !== 0 )
                    {
                        break ;
                    }
                }
                else
                {
                    fread( $handle , $blocks * 512 ) ;
                }
            }

            // A GNU long name or long link name, and a pax header, each describe the entry that
            // comes next rather than themselves.

            if ( $type === 'L' )
            {
                $longName = trim( substr( $data , 0 , $size ) , "\0" ) ;
                continue ;
            }

            if ( $type === 'K' )
            {
                $longLink = trim( substr( $data , 0 , $size ) , "\0" ) ;
                continue ;
            }

            if ( $type === 'x' )
            {
                $records   = tarPaxRecords( substr( $data , 0 , $size ) ) ;
                $paxName   = $records[ 'path' ]     ?? null ;
                $paxTarget = $records[ 'linkpath' ] ?? null ;
                continue ;
            }

            if ( $type === '1' || $type === '2' )
            {
                $prefix = trim( substr( $header , 345 , 155 ) , "\0" ) ;

                if ( $prefix !== '' )
                {
                    $name = $prefix . '/' . $name ;
                }

                $target = trim( substr( $header , 157 , 100 ) , "\0" ) ;

                $links[] =
                [
                    'name'   => $paxName   ?? $longName ?? $name ,
                    'target' => $paxTarget ?? $longLink ?? $target ,
                    'hard'   => $type === '1' ,
                ] ;
            }

            $longName  = null ;
            $longLink  = null ;
            $paxName   = null ;
            $paxTarget = null ;
        }
    }
    finally
    {
        fclose( $handle ) ;
    }

    return $links ;
}

/**
 * The size a tar header declares.
 *
 * Octal, as the format has always had it, unless the field's high bit is set: GNU writes a
 * base-256 integer there for a size the twelve octal digits cannot hold.
 *
 * @param string $header One 512-byte tar header.
 *
 * @return int The declared size in bytes.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.6.0
 */
function tarHeaderSize( string $header ): int
{
    $field = substr( $header , 124 , 12 ) ;

    if ( ( ord( $field[ 0 ] ) & 0x80 ) !== 0 )
    {
        $size = 0 ;

        for ( $i = 1 ; $i < 12 ; $i++ )
        {
            $size = ( $size << 8 ) | ord( $field[ $i ] ) ;
        }

        return $size ;
    }

    $octal = trim( $field , "\0 " ) ;

    return $octal === '' ? 0 : (int) octdec( $octal ) ;
}

/**
 * The records of a pax extended header.
 *
 * Each record is `"<length> <keyword>=<value>\n"`, and the length counts itself, so the records
 * are walked by the length they declare rather than split on a separator a value may contain.
 *
 * @param string $data The pax header's data blocks, trimmed to its declared size.
 *
 * @return array<string, string> The records, keyed by keyword.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.6.0
 */
function tarPaxRecords( string $data ): array
{
    $records = [] ;
    $offset  = 0 ;
    $length  = strlen( $data ) ;

    while ( $offset < $length )
    {
        $space = strpos( $data , ' ' , $offset ) ;

        if ( $space === false )
        {
            break ;
        }

        $declared = (int) substr( $data , $offset , $space - $offset ) ;

        if ( $declared <= 0 || $offset + $declared > $length )
        {
            break ;
        }

        $record = substr( $data , $space + 1 , $offset + $declared - $space - 2 ) ;
        $equals = strpos( $record , '=' ) ;

        if ( $equals !== false )
        {
            $records[ substr( $record , 0 , $equals ) ] = substr( $record , $equals + 1 ) ;
        }

        $offset += $declared ;
    }

    return $records ;
}

/**
 * One whole 512-byte block, or `null` when the stream has no more to give.
 *
 * `fread()` on a compressed stream answers with what the inflater has ready rather than what was
 * asked for, so a block is gathered until it is whole.
 *
 * @param resource $handle An open archive stream.
 *
 * @return string|null The block, or `null` at the end of the stream.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.6.0
 */
function tarReadBlock( $handle ): ?string
{
    $block = '' ;

    while ( strlen( $block ) < 512 )
    {
        $chunk = fread( $handle , 512 - strlen( $block ) ) ;

        if ( $chunk === false || $chunk === '' )
        {
            return null ;
        }

        $block .= $chunk ;
    }

    return $block ;
}
