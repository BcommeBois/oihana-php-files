<?php
/**
 * Check every internal link of the wiki: the files they point at, and the anchors.
 *
 * Usage:
 *   php tools/check-wiki-links.php [wiki-directory]
 *
 * Defaults:
 *   wiki-directory -> wiki
 *
 * Exits 0 when every link resolves, 1 otherwise, listing each failure — so it can
 * be run in CI or before a release. `composer wiki:links`.
 *
 * ### Why a tool and not a careful read
 *
 * A broken anchor looks exactly like a working one. Run for the first time on
 * 2026-10-24 it found **eleven** links that could not resolve, none of them recent:
 * a function linked in the page it is not documented in, four links to a numbered
 * section whose anchor carries the number, a `composer.json` reached one level short
 * of the repository root, a `../` too many, and a French heading whose anchor carries
 * its translation. Nobody reading those pages would have noticed.
 *
 * ### The anchor algorithm, and the trap in it
 *
 * GitHub lowercases the heading, drops everything that is not a letter, a digit, a
 * space, a hyphen or an underscore, then turns **each** space into a hyphen. The
 * last word matters: runs of spaces are not collapsed, so a punctuation mark
 * followed by a space leaves a **double** hyphen — `## Patterns : glob or regex`
 * becomes `patterns--glob-or-regex`. The first version of this check collapsed them
 * and reported two perfectly good links as broken, which nearly got "fixed".
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */

$wikiDirectory = $argv[ 1 ] ?? 'wiki' ;

if ( !is_dir( $wikiDirectory ) )
{
    fwrite( STDERR , "Wiki directory not found: {$wikiDirectory}\n" ) ;
    exit( 1 ) ;
}

/**
 * The anchor GitHub gives a heading.
 */
function wikiAnchor( string $heading ) :string
{
    $anchor = mb_strtolower( trim( str_replace( '`' , '' , $heading ) ) ) ;
    $anchor = preg_replace( '/[^\p{L}\p{N}\s\-_]/u' , '' , $anchor ) ;

    return str_replace( ' ' , '-' , $anchor ) ;
}

$pages = [] ;

$iterator = new RecursiveIteratorIterator
(
    new RecursiveDirectoryIterator( $wikiDirectory , FilesystemIterator::SKIP_DOTS )
) ;

foreach ( $iterator as $file )
{
    if ( $file->isFile() && strtolower( $file->getExtension() ) === 'md' )
    {
        $pages[] = $file->getPathname() ;
    }
}

sort( $pages ) ;

$anchors = [] ;

foreach ( $pages as $page )
{
    $anchors[ $page ] = [] ;

    if ( preg_match_all( '/^#{1,6}\s+(.*)$/mu' , (string) file_get_contents( $page ) , $matches ) )
    {
        foreach ( $matches[ 1 ] as $heading )
        {
            $anchors[ $page ][ wikiAnchor( $heading ) ] = true ;
        }
    }
}

$broken = [] ;

foreach ( $pages as $page )
{
    if ( !preg_match_all( '/\]\(([^)\s]+)\)/u' , (string) file_get_contents( $page ) , $matches ) )
    {
        continue ;
    }

    foreach ( $matches[ 1 ] as $target )
    {
        if ( preg_match( '#^(https?:|mailto:)#' , $target ) === 1 )
        {
            continue ;
        }

        [ $filePart , $anchor ] = array_pad( explode( '#' , $target , 2 ) , 2 , '' ) ;

        $destination = $filePart === ''
                     ? $page
                     : realpath( dirname( $page ) . DIRECTORY_SEPARATOR . $filePart ) ;

        if ( $filePart !== '' && $destination === false )
        {
            $broken[] = [ $page , $target , 'file not found' ] ;
            continue ;
        }

        if ( $anchor === '' )
        {
            continue ;
        }

        $key = $destination === $page ? $page : null ;

        foreach ( $anchors as $candidate => $_ )
        {
            if ( realpath( $candidate ) === $destination )
            {
                $key = $candidate ;
                break ;
            }
        }

        // An anchor into a file that is not a wiki page — a test, composer.json — is
        // not something this can check, and not something to report.
        if ( $key === null )
        {
            continue ;
        }

        if ( !isset( $anchors[ $key ][ $anchor ] ) )
        {
            $broken[] = [ $page , $target , 'anchor not found' ] ;
        }
    }
}

printf( "%d page(s) checked.\n" , count( $pages ) ) ;

if ( $broken === [] )
{
    echo "Every internal link resolves.\n" ;
    exit( 0 ) ;
}

printf( "\n%d broken link(s):\n" , count( $broken ) ) ;

foreach ( $broken as [ $page , $target , $reason ] )
{
    printf( "  %s\n      -> %s   (%s)\n" , $page , $target , $reason ) ;
}

exit( 1 ) ;
