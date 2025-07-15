<?php

namespace oihana\files ;

use InvalidArgumentException;
use oihana\enums\Char;

/**
 * Split a filename or URI into its scheme (if any) and hierarchical part.
 *
 * Logic
 * -----
 * • Detect the first “://” only once – no array allocation if not present.
 * • Accept schemes that match RFC‑3986     `[A‑Za‑z][A‑Za‑z0‑9+\-.]*`.
 * • Return `[$scheme, $hierarchy]`, where `$scheme` is `null` when absent.
 *
 * @param string $filename A path or URI such as `file:///tmp/app.log` or `/etc/hosts`.
 * @return array{0: ?string, 1: string}
 *
 * @throws InvalidArgumentException if the scheme is malformed (e.g. '1http://')
 *
 * @example getSchemeAndHierarchy('s3://bucket/folder/img');    // ['s3',   'bucket/folder/img']
 * @example getSchemeAndHierarchy('/home/user/report.pdf');     // [null,  '/home/user/report.pdf']
 * @example getSchemeAndHierarchy('C:\\Windows\\notepad.exe');  // [null,  'C:\\Windows\\notepad.exe']
 * @example getSchemeAndHierarchy('file:///tmp/cache.db');      // ['file', '/tmp/cache.db']
 */
function getSchemeAndHierarchy( string $filename ): array
{
    $pos = strpos($filename, '://' ) ;

    if ($pos === false) // No separator → plain path
    {
        return [ null , $filename ] ;
    }

    $scheme    = substr( $filename , 0 , $pos ) ;
    $hierarchy = substr( $filename , $pos + 3 ) ; // skip "://"

    // Validate scheme (optional but helps catch typos)
    if ($scheme !== Char::EMPTY && !preg_match('/^[A-Za-z][A-Za-z0-9+.\-]*$/', $scheme ) )
    {
        throw new InvalidArgumentException("Malformed scheme: '{$scheme}'");
    }

    return [$scheme !== '' ? $scheme : null, $hierarchy];
}