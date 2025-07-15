<?php

namespace oihana\files ;

use SplFileInfo ;
use function oihana\core\strings\lower;

/**
 * Sorts an array of SplFileInfo objects.
 * @param SplFileInfo[]            &$files <p>Array of files to sort (modified in‑place).</p>
 * @param callable|string|array    $sort   <p>One of:</p>
 * <ul>
 *  <li><b>callable</b> : custom compare function, ex: <code>fn(SplFileInfo $a, SplFileInfo $b): int</code></li>
 *  <li><b>string</b> : single built‑in key</br><code>'name' | 'ci_name' | 'extension' | 'size' | 'type' | 'atime' | 'ctime' | 'mtime'</code></li></li>
 *  <li><b>array</b> : ordered list of such keys for multi‑criteria sorting e.g. ['type', 'name']   or  ['extension','size']</li>
 * </ul>
 * @param ?string $order <p>The direction of the sort method 'asc' (default) or 'desc'.</p>
 * @return void
 * @examples
 * ```php
 * // 1) Sort by filename ascending
 * sortFiles($files, 'name');
 *
 * // 2) Case‑insensitive filename descending
 * sortFiles($files, 'ci_name', 'desc');
 *
 * // 3) Sort by extension then by size
 * sortFiles($files, ['extension', 'size']);
 *
 * // 4) Custom comparator: modified time descending
 * sortFiles($files, fn($a, $b) => $a->getMTime() <=> $b->getMTime(), 'desc');
 *
 * // 5) Type then case‑insensitive name
 * sortFiles($files, ['type', 'ci_name']);
 * ```
 */
function sortFiles( array &$files, callable|string|array $sort , ?string $order = 'asc' ): void
{
    $order = lower( $order ?? 'asc' ) ;

    if ( is_callable( $sort ) )
    {
        usort($files, $sort) ;
        if ( strtolower( $order ) === 'desc' )
        {
            $files = array_reverse( $files );
        }
        return;
    }

    /** @var string[] $criteria */
    $criteria = (array) $sort ; // normalise
    $ratio   = strtolower( $order ) === 'desc' ? -1 : 1 ;

    usort($files, function ( SplFileInfo $a , SplFileInfo $b ) use ( $criteria , $ratio ): int
    {
        foreach ( $criteria as $key )
        {
            $cmp = match ($key)
            {
                'name'       => strcmp($a->getFilename(), $b->getFilename()),
                'ci_name'    => strcasecmp($a->getFilename(), $b->getFilename()),
                'extension'  => strcasecmp($a->getExtension(), $b->getExtension()),
                'size'       => $a->getSize()   <=> $b->getSize(),
                'type'       => strcmp($a->getType(), $b->getType()),
                'atime'      => $a->getATime() <=> $b->getATime(),
                'ctime'      => $a->getCTime() <=> $b->getCTime(),
                'mtime'      => $a->getMTime() <=> $b->getMTime(),
                default      => 0 // unknown key → ignore
            };

            if ( $cmp !== 0 )
            {
                return $ratio * $cmp ; // stop at first non‑zero difference
            }
        }
        return 0 ; // all criteria equal
    });
}