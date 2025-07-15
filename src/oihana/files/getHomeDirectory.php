<?php

namespace oihana\files ;

use RuntimeException;
use function oihana\files\path\canonicalizePath;

/**
 * Returns the current user’s home directory as a **canonical** path.
 *
 * Resolution strategy – in order :
 *
 * 1. **Unix / macOS / Linux**
 *    Uses the `HOME` environment variable if it is set and non‑empty.
 * 2. **Windows (≥ XP)**
 *    Combines `HOMEDRIVE` + `HOMEPATH` (e.g.  `C:` + `\Users\John`) if both are available.
 * 3. **Failure**
 *    Throws a `RuntimeException` when no recognised combination is found.
 *
 * The resulting string is passed through {@see canonicalizePath()} so that
 * path separators are normalized (backslashes → slashes) and redundant slashes
 * are removed.
 *
 * @return string Canonical absolute path to the user’s home directory.
 *
 * @throws RuntimeException When the home directory cannot be determined.
 *
 * @example
 * ```php
 * $home = getHomeDirectory(); // "/home/alice" or "C:/Users/Alice"
 * ```
 */
function getHomeDirectory(): string
{
    // For UNIX support
    if ( getenv('HOME') )
    {
        return canonicalizePath( getenv('HOME') );
    }

    // For >= Windows8 support
    if ( getenv('HOMEDRIVE') && getenv('HOMEPATH') )
    {
        return canonicalizePath(getenv('HOMEDRIVE').getenv('HOMEPATH') ) ;
    }

    throw new RuntimeException("Cannot find the home directory path: Your environment or operating system isn't supported.") ;
}