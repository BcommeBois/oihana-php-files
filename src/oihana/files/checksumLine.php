<?php

namespace oihana\files ;

/**
 * Writes the line that records a file's checksum, as `sha256sum` writes it.
 *
 * The hash in lowercase, two spaces, the file name, a line feed: `sha256sum -c` (or `md5sum -c`,
 * `shasum -c`…) checks it by hand, beside the file, without PHP. Give the name alone, with no
 * directory, and the line still checks once both files are copied elsewhere.
 *
 * `sha256sum` writes a name holding a line feed or a backslash another way (escaped, with a
 * backslash before the line): such a name is not supported here.
 *
 * @param string $hash     The checksum, hexadecimal, e.g. from {@see fileChecksum()}.
 * @param string $filename The name of the file.
 *
 * @return string The line, ending with a line feed.
 *
 * @example
 * ```php
 * use function oihana\files\checksumLine;
 * use function oihana\files\fileChecksum;
 *
 * file_put_contents( '/data/report.pdf.sha256' , checksumLine( fileChecksum( '/data/report.pdf' ) , 'report.pdf' ) ) ;
 * // "<64 hexadecimal characters>  report.pdf\n" ; then `cd /data && sha256sum -c report.pdf.sha256`
 * ```
 *
 * @see readChecksumLine()
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function checksumLine( string $hash , string $filename ) :string
{
    return sprintf( "%s  %s\n" , strtolower( $hash ) , $filename ) ;
}
