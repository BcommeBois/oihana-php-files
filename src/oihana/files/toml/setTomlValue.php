<?php

namespace oihana\files\toml ;

/**
 * Sets a string value in a TOML document, inside one table only, keeping the rest of the text.
 *
 * The replacement is anchored to the requested `[section]`: a key is only rewritten between that
 * header and the next one. This matters because the same key name often recurs across tables —
 * `host` or `pass` under `[database]`, `[smtp]` and `[mysql]` — and an unanchored replacement
 * silently overwrites unrelated credentials.
 *
 * The document is edited as text, not parsed and written back, so nothing else moves: the
 * alignment before `=` and any trailing comment are kept, and only the quoted value is swapped.
 * When the key is absent, it is added at the end of its section, before the blank lines that
 * close it; when the section itself is absent, it is added at the end of the document.
 * Line endings follow the document's: CRLF when it has one, LF otherwise.
 *
 * Scope: a key written as a single-line basic (`"…"`) or literal (`'…'`) string. A key holding a
 * number, a boolean, an array or a multi-line string is not recognised, and a new line is added
 * for it instead.
 *
 * @param string      $content The TOML document.
 * @param string|null $section The table name (e.g. `database`, or `app.database`), compared
 *                             without regard to case; null for the root table, above the first
 *                             header.
 * @param string      $key     The key to set.
 * @param string      $value   The raw value. It is written as a basic string, escaped by
 *                             {@see escapeTomlString()}.
 *
 * @return string The updated document.
 *
 * @example
 * ```php
 * use function oihana\files\toml\setTomlValue;
 *
 * $toml = "[database]\nhost = \"localhost\" # local\n\n[smtp]\nhost = \"mail\"\n" ;
 *
 * echo setTomlValue( $toml , 'database' , 'host' , 'db.example.com' ) ;
 * // [database]
 * // host = "db.example.com" # local
 * //
 * // [smtp]
 * // host = "mail"
 * ```
 *
 * @package oihana\files\toml
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.7.0
 */
function setTomlValue( string $content , ?string $section , string $key , string $value ) :string
{
    $lines      = preg_split( '/\R/' , $content ) ;
    $eol        = str_contains( $content , "\r\n" ) ? "\r\n" : "\n" ;
    $target     = $section === null ? null : strtolower( $section ) ;
    $inSection  = $target === null ;
    $sectionEnd = null ;

    $quoted = '"' . escapeTomlString( $value ) . '"' ;

    $pattern = '/^(?<prefix>\s*' . preg_quote( $key , '/' ) . '\s*=\s*)'
             . '(?<quote>["\'])(?:\\\\.|(?!\k<quote>).)*\k<quote>'
             . '(?<suffix>.*)$/' ;

    foreach ( $lines as $index => $line )
    {
        // A table header: [database], [app.database], …

        if ( preg_match( '/^\s*\[\s*([^]]+?)\s*]/' , $line , $matches ) )
        {
            // Leaving the target section: remember where it ended.

            if ( $inSection && $sectionEnd === null )
            {
                $sectionEnd = $index ;
            }

            $inSection = strtolower( $matches[ 1 ] ) === $target ;

            continue ;
        }

        if ( $inSection && preg_match( $pattern , $line , $matches ) )
        {
            $lines[ $index ] = $matches[ 'prefix' ] . $quoted . $matches[ 'suffix' ] ;

            return implode( $eol , $lines ) ;
        }
    }

    // The key is not there: add it at the end of its section, before the blank lines.

    if ( $inSection || $sectionEnd !== null )
    {
        $insertAt = $sectionEnd ?? count( $lines ) ;

        while ( $insertAt > 0 && trim( $lines[ $insertAt - 1 ] ) === '' )
        {
            $insertAt-- ;
        }

        array_splice( $lines , $insertAt , 0 , [ $key . ' = ' . $quoted ] ) ;

        return implode( $eol , $lines ) ;
    }

    // The section is not there: add it.

    return rtrim( $content , "\r\n" ) . $eol . $eol
         . '[' . $section . ']' . $eol
         . $key . ' = ' . $quoted . $eol ;
}
