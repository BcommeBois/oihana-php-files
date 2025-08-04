<?php

namespace oihana\files ;

use oihana\files\enums\OwnershipInfo;
use RuntimeException;

/**
 * Retrieves the current ownership information of a given file or directory.
 *
 * Returns an `OwnershipInfo` object containing both numeric UID/GID and
 * their corresponding human-readable owner and group names (if resolvable).
 *
 * Requires the `posix` extension to resolve usernames and group names;
 * otherwise, `owner` and `group` may be `null`.
 *
 * @param string $path Absolute or relative path to the file or directory.
 *
 * @return OwnershipInfo Object containing UID, GID, and optionally owner and group names.
 *
 * @throws RuntimeException If the given path does not exist.
 *
 * @example
 * ```php
 * use oihana\files\getOwnershipInfo;
 *
 * $info = getOwnershipInfo('/var/www/html');
 *
 * echo $info->owner; // 'www-data'
 * echo $info->uid;   // 33
 * echo $info;        // www-data:www-data (33:33)
 * ```
 */
function getOwnershipInfo( string $path ): OwnershipInfo
{
    if ( !file_exists( $path ) )
    {
        throw new RuntimeException("Path '$path' does not exist." ) ;
    }

    $uid = fileowner( $path ) ;
    $gid = filegroup( $path ) ;

    $owner = function_exists('posix_getpwuid' ) ? posix_getpwuid( $uid )[ 'name' ] ?? null : null ;
    $group = function_exists('posix_getgrgid' ) ? posix_getgrgid( $gid )[ 'name' ] ?? null : null ;

    return new OwnershipInfo
    ([
        OwnershipInfo::OWNER => $owner ,
        OwnershipInfo::GROUP => $group ,
        OwnershipInfo::UID   => $uid   ,
        OwnershipInfo::GID   => $gid   ,
    ]);
}