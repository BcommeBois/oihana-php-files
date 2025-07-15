<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;

/**
 * Creates (or returns if already present) a directory inside the system temporary folder.
 *
 * The sub‑path is appended to <code>sys_get_temp_dir()</code> de la même manière que
 * {@see getTemporaryDirectory()} :
 * <ul>
 * <li><code>null</code> → the temp dir itself</li>
 * <li><code>'cache'</code> → <code>/tmp/cache</code></li>
 * <li><code>['my', 'app']</code> → <code>/tmp/my/app</code></li>
 * </ul>
 *
 * @param string|string[]|null $path Optional sub‑directory path segments.
 * @param int $permission Octal mode for <code>mkdir()</code> (default: <code>0755</code>).
 *
 * @return string Full path to the (existing or newly created) temporary directory.
 *
 * @throws DirectoryException If creation fails or the directory is still missing afterwards.
 *
 * @example
 * ```php
 * // 1) Ensure /tmp/reports exists
 * $reportsDir = makeTemporaryDirectory('reports');
 *
 * // 2) Ensure /tmp/my/app/cache exists
 * $cacheDir = makeTemporaryDirectory(['my','app','cache'], 0700);
 *
 * // 3) Just return /tmp itself
 * $tmp = makeTemporaryDirectory(null);
 * ```
 */
function makeTemporaryDirectory( string|array|null $path , int $permission = 0755 ): string
{
    $directory = getTemporaryDirectory( $path ) ;

    if ( is_dir( $directory ) )
    {
        return $directory ;
    }

    if ( !@mkdir( $directory , $permission , true ) && !is_dir( $directory ) )
    {
        throw new DirectoryException ( sprintf('Failed to create temporary directory at "%s".' , $directory ) ) ;
    }

    return $directory;
}