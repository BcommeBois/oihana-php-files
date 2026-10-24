<?php

namespace oihana\files\openssl ;

/**
 * Returns the number of bytes this process may reasonably allocate, or `null` when
 * it cannot be told.
 *
 * **Why this exists.** The legacy V1/V2 read path loads the whole file into memory
 * and peaks at about {@see \oihana\files\openssl\enums\EncryptionFormat::LEGACY_MEMORY_FACTOR}
 * times its size. Past the budget, PHP either throws a fatal "Allowed memory size
 * exhausted" or — when `memory_limit` is `-1` — the kernel OOM killer takes the
 * process down with no exception and no stack trace, so nothing can be reported.
 * Measured on 2026-10-24 in a 3.8 GB VM: a 1 GB file logged
 * `Out of memory: Killed process (php)` in `dmesg` and nothing anywhere else.
 * Knowing the budget is what lets {@see OpenSSLFileEncryption::decrypt()} refuse
 * with a message instead of disappearing.
 *
 * Two sources, in order:
 *
 * 1. **`memory_limit`** — the limit PHP enforces itself, read through
 *    {@see parseMemoryLimit}. Any positive value wins:
 *    it is the ceiling that actually applies, however much RAM the host has.
 * 2. **`/proc/meminfo`'s `MemAvailable`** — consulted only when `memory_limit` is
 *    `-1` (unlimited), where the real ceiling is the machine's free memory. Linux
 *    only; elsewhere the function returns `null`.
 *
 * `null` means *unknown*, not *unlimited*: callers should let the operation through
 * rather than refuse on a number they do not have.
 *
 * @param string|null $memoryLimit A `memory_limit`-style value to read instead of the
 *                                 ini setting (`'512M'`, `'-1'`, `'1G'`…). Testing seam;
 *                                 `null` reads `ini_get('memory_limit')`.
 * @param string      $meminfoPath Path to read `MemAvailable` from. Testing seam.
 *
 * @return int|null The budget in bytes, or `null` when no source gives a figure.
 *
 * @example
 * ```php
 * use function oihana\files\openssl\availableMemoryBytes;
 *
 * availableMemoryBytes() ;              // 268435456 with memory_limit = 256M
 * availableMemoryBytes( '-1' ) ;        // MemAvailable on Linux, null on macOS
 * ```
 *
 * @package oihana\files\openssl
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function availableMemoryBytes( ?string $memoryLimit = null , string $meminfoPath = '/proc/meminfo' ): ?int
{
    $limit = parseMemoryLimit( $memoryLimit ?? (string) ini_get('memory_limit') ) ;

    if ( $limit !== null )
    {
        return $limit ;
    }

    // memory_limit is unlimited: the real ceiling is what the machine has left.
    if ( !is_readable( $meminfoPath ) )
    {
        return null ;
    }

    $meminfo = @file_get_contents( $meminfoPath ) ;
    if ( $meminfo === false )
    {
        // is_readable() just passed; an unreadable file here is a TOCTOU race.
        // @codeCoverageIgnoreStart
        return null ;
        // @codeCoverageIgnoreEnd
    }

    if ( preg_match( '/^MemAvailable:\s+(\d+)\s*kB/mi' , $meminfo , $matches ) !== 1 )
    {
        return null ;
    }

    return (int) $matches[ 1 ] * 1024 ;
}
