<?php

namespace oihana\files\path ;

use oihana\enums\Char;

/**
 * Extracts the canonical path segments of « $pathWithoutRoot ».
 *
 * This method breaks down the input path into segments, and processes them to remove:
 * - Redundant current directory references (`.`)
 * - Properly resolvable parent references (`..`)
 *
 * It does **not** access the filesystem and works purely on string logic.
 *
 * @param string $root             The base root path. Used to determine whether
 *                                 `..` segments can be collapsed.
 *                                 If empty, leading `..` segments will be preserved.
 * @param string $pathWithoutRoot The input path (already stripped of the root part).
 *
 * @return string[] An array of canonical path segments.
 *
 * @example
 * ```php
 * extractCanonicalParts('/var/www', 'project/../cache/./logs')
 * // Returns: ['cache', 'logs']
 *
 * extractCanonicalParts('', '../../folder')
 * // Returns: ['..', '..', 'folder']
 * ```
 *
 * @note This function does **not** validate that the resulting path actually exists.
 */
function extractCanonicalParts( string $root , string $pathWithoutRoot ): array
{
    $parts = explode(Char::SLASH , $pathWithoutRoot ) ;

    $canonical = [];

    foreach ( $parts as $part )
    {
        // IGNORE empty or "."
        if ( $part === Char::EMPTY || $part === Char::DOT )
        {
            continue;
        }

        if
        (
            // Collapse ".." when possible
            $part === Char::DOUBLE_DOT && !empty( $canonical )  && end($canonical) !== Char::DOUBLE_DOT
        )
        {
            array_pop( $canonical ) ;
            continue;
        }

        // Keep segment if (a) not ".." OR (b) root is empty (relative path)
        if ($part !== Char::DOUBLE_DOT || $root === Char::EMPTY)
        {
            $canonical[] = $part ;
        }
    }

    return $canonical ;
}
